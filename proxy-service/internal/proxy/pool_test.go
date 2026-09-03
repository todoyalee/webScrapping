package proxy

import (
	"sync"
	"testing"
	"time"
)

func TestNextRotatesRoundRobin(t *testing.T) {
	p := New([]string{"http://a:1", "http://b:2", "http://c:3"}, 3, time.Minute)

	got := make([]string, 6)
	for i := range got {
		v, err := p.Next()
		if err != nil {
			t.Fatalf("Next() error: %v", err)
		}
		got[i] = v
	}

	want := []string{
		"http://a:1", "http://b:2", "http://c:3",
		"http://a:1", "http://b:2", "http://c:3",
	}
	for i := range want {
		if got[i] != want[i] {
			t.Fatalf("rotation[%d] = %q, want %q", i, got[i], want[i])
		}
	}
}

func TestNextEmptyPool(t *testing.T) {
	p := New(nil, 3, time.Minute)
	if _, err := p.Next(); err != ErrNoProxyAvailable {
		t.Fatalf("want ErrNoProxyAvailable, got %v", err)
	}
}

func TestInvalidSeedsAreSkipped(t *testing.T) {
	p := New([]string{"http://good:8080", "::not-a-url", "missing-scheme:1"}, 3, time.Minute)
	if p.Len() != 1 {
		t.Fatalf("want 1 valid proxy, got %d", p.Len())
	}
}

func TestReportQuarantinesAfterMaxFailures(t *testing.T) {
	now := time.Unix(0, 0)
	clock := func() time.Time { return now }
	p := New([]string{"http://a:1", "http://b:2"}, 2, time.Minute, WithClock(clock))

	// Fail "a" twice -> quarantined.
	p.Report("http://a:1", false)
	p.Report("http://a:1", false)

	// Only "b" is handed out now, repeatedly.
	for i := 0; i < 3; i++ {
		v, err := p.Next()
		if err != nil {
			t.Fatalf("Next() error: %v", err)
		}
		if v != "http://b:2" {
			t.Fatalf("want http://b:2 while a is quarantined, got %q", v)
		}
	}

	// After the cooldown elapses, "a" returns to rotation.
	now = now.Add(2 * time.Minute)
	seen := map[string]bool{}
	for i := 0; i < 2; i++ {
		v, _ := p.Next()
		seen[v] = true
	}
	if !seen["http://a:1"] {
		t.Fatalf("proxy a should be back in rotation after cooldown")
	}
}

func TestReportSuccessClearsFailures(t *testing.T) {
	p := New([]string{"http://a:1"}, 2, time.Minute)
	p.Report("http://a:1", false)
	p.Report("http://a:1", true) // reset
	p.Report("http://a:1", false)

	if _, err := p.Next(); err != nil {
		t.Fatalf("proxy should still be healthy after a success reset, got %v", err)
	}
	if s := p.Snapshot(); s[0].Failures != 1 {
		t.Fatalf("failures = %d, want 1", s[0].Failures)
	}
}

func TestAddRejectsDuplicatesAndJunk(t *testing.T) {
	p := New(nil, 3, time.Minute)
	if err := p.Add("http://a:1"); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if err := p.Add("http://a:1"); err != nil {
		t.Fatalf("duplicate Add should be a no-op, got %v", err)
	}
	if err := p.Add("nonsense"); err == nil {
		t.Fatalf("expected error for malformed url")
	}
	if p.Len() != 1 {
		t.Fatalf("want 1 proxy, got %d", p.Len())
	}
}

func TestConcurrentAccessIsRaceFree(t *testing.T) {
	p := New([]string{"http://a:1", "http://b:2", "http://c:3"}, 3, time.Minute)
	var wg sync.WaitGroup
	for i := 0; i < 50; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			_, _ = p.Next()
			p.Report("http://a:1", false)
			_ = p.Snapshot()
		}()
	}
	wg.Wait()
}
