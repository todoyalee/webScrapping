// Package config loads the proxy-service configuration from environment
// variables, applying sane defaults so the service runs with zero setup.
package config

import (
	"os"
	"strconv"
	"strings"
	"time"
)

// Config holds every tunable knob for the service.
type Config struct {
	// Addr is the host:port the HTTP server listens on.
	Addr string

	// Seeds is the initial set of proxy URLs (e.g. "http://1.2.3.4:8080").
	Seeds []string

	// SeedFile, when set, is a path to a newline-separated list of proxy URLs
	// that is merged with Seeds on startup.
	SeedFile string

	// APIToken, when non-empty, is required as "Authorization: Bearer <token>"
	// on every request except the health check.
	APIToken string

	// MaxFailures is how many consecutive failures quarantine a proxy.
	MaxFailures int

	// Cooldown is how long a quarantined proxy is skipped before it is retried.
	Cooldown time.Duration
}

// Load reads the configuration from the process environment.
func Load() Config {
	return Config{
		Addr:        env("PROXY_SERVICE_ADDR", ":9000"),
		Seeds:       splitAndTrim(os.Getenv("PROXY_SEEDS")),
		SeedFile:    os.Getenv("PROXY_SEED_FILE"),
		APIToken:    os.Getenv("PROXY_SERVICE_TOKEN"),
		MaxFailures: envInt("PROXY_MAX_FAILURES", 3),
		Cooldown:    envDuration("PROXY_COOLDOWN", 60*time.Second),
	}
}

func env(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func envInt(key string, fallback int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return fallback
}

func envDuration(key string, fallback time.Duration) time.Duration {
	if v := os.Getenv(key); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			return d
		}
	}
	return fallback
}

func splitAndTrim(csv string) []string {
	if csv == "" {
		return nil
	}
	parts := strings.Split(csv, ",")
	out := make([]string, 0, len(parts))
	for _, p := range parts {
		if p = strings.TrimSpace(p); p != "" {
			out = append(out, p)
		}
	}
	return out
}
