// Package proxy implements a thread-safe, round-robin pool of upstream proxies
// with lightweight health tracking. It is the core of the proxy-management
// microservice: the Laravel scraper asks the pool for "the next proxy" before
// every outbound request and reports back the ones that fail.
package proxy

import (
	"errors"
	"net/url"
	"sync"
	"time"
)

// ErrNoProxyAvailable is returned by Next when every proxy is quarantined.
var ErrNoProxyAvailable = errors.New("no healthy proxy available")

// Clock is the time source used by the pool. Tests swap in a fake.
type Clock func() time.Time

type entry struct {
	rawURL           string
	failures         int
	quarantinedUntil time.Time // zero == healthy; otherwise skip until this instant
}

// Stats is a point-in-time snapshot of a single proxy's health.
type Stats struct {
	URL         string `json:"url"`
	Failures    int    `json:"failures"`
	Healthy     bool   `json:"healthy"`
	Quarantined string `json:"quarantined_until,omitempty"`
}

// Pool is a rotating collection of proxies. All methods are safe for concurrent
// use.
type Pool struct {
	mu          sync.Mutex
	entries     []*entry
	index       int
	maxFailures int
	cooldown    time.Duration
	now         Clock
}

// Option configures a Pool.
type Option func(*Pool)

// WithClock overrides the pool's time source (used in tests).
func WithClock(c Clock) Option { return func(p *Pool) { p.now = c } }

// New builds a pool from the given proxy URLs. Invalid URLs are skipped so a
// single bad seed cannot take the service down.
func New(urls []string, maxFailures int, cooldown time.Duration, opts ...Option) *Pool {
	p := &Pool{
		maxFailures: maxFailures,
		cooldown:    cooldown,
		now:         time.Now,
	}
	for _, o := range opts {
		o(p)
	}
	for _, u := range urls {
		_ = p.Add(u) // seed errors are non-fatal by design
	}
	return p
}

// Add inserts a proxy URL if it is well-formed and not already present.
func (p *Pool) Add(raw string) error {
	u, err := url.Parse(raw)
	if err != nil || u.Scheme == "" || u.Host == "" {
		return errors.New("invalid proxy url: " + raw)
	}
	p.mu.Lock()
	defer p.mu.Unlock()
	for _, e := range p.entries {
		if e.rawURL == raw {
			return nil
		}
	}
	p.entries = append(p.entries, &entry{rawURL: raw})
	return nil
}

// Next returns the next healthy proxy in round-robin order. It walks at most one
// full lap; if every proxy is quarantined it returns ErrNoProxyAvailable.
func (p *Pool) Next() (string, error) {
	p.mu.Lock()
	defer p.mu.Unlock()

	n := len(p.entries)
	if n == 0 {
		return "", ErrNoProxyAvailable
	}

	now := p.now()
	for i := 0; i < n; i++ {
		e := p.entries[p.index]
		p.index = (p.index + 1) % n
		if e.quarantinedUntil.IsZero() || now.After(e.quarantinedUntil) {
			e.quarantinedUntil = time.Time{} // cooldown elapsed: back in rotation
			return e.rawURL, nil
		}
	}
	return "", ErrNoProxyAvailable
}

// Report records the outcome of using a proxy. A success clears its failure
// count; enough consecutive failures quarantine it for the cooldown window.
func (p *Pool) Report(raw string, ok bool) {
	p.mu.Lock()
	defer p.mu.Unlock()
	for _, e := range p.entries {
		if e.rawURL != raw {
			continue
		}
		if ok {
			e.failures = 0
			e.quarantinedUntil = time.Time{}
			return
		}
		e.failures++
		if e.failures >= p.maxFailures {
			e.quarantinedUntil = p.now().Add(p.cooldown)
		}
		return
	}
}

// Snapshot returns the health of every proxy, in insertion order.
func (p *Pool) Snapshot() []Stats {
	p.mu.Lock()
	defer p.mu.Unlock()
	now := p.now()
	out := make([]Stats, 0, len(p.entries))
	for _, e := range p.entries {
		healthy := e.quarantinedUntil.IsZero() || now.After(e.quarantinedUntil)
		s := Stats{URL: e.rawURL, Failures: e.failures, Healthy: healthy}
		if !healthy {
			s.Quarantined = e.quarantinedUntil.UTC().Format(time.RFC3339)
		}
		out = append(out, s)
	}
	return out
}

// Len reports how many proxies are in the pool, healthy or not.
func (p *Pool) Len() int {
	p.mu.Lock()
	defer p.mu.Unlock()
	return len(p.entries)
}
