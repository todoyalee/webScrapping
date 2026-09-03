// Command proxy-service is a small HTTP microservice that hands out upstream
// proxies to the Laravel scraper in round-robin order and quarantines the ones
// that fail. It has no dependencies beyond the Go standard library.
package main

import (
	"bufio"
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"github.com/mohamedbelkouri/proxyscrape/proxy-service/internal/config"
	"github.com/mohamedbelkouri/proxyscrape/proxy-service/internal/httpapi"
	"github.com/mohamedbelkouri/proxyscrape/proxy-service/internal/proxy"
)

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))

	if err := run(logger); err != nil {
		logger.Error("fatal", "error", err)
		os.Exit(1)
	}
}

func run(logger *slog.Logger) error {
	cfg := config.Load()

	seeds := cfg.Seeds
	if cfg.SeedFile != "" {
		fromFile, err := readSeedFile(cfg.SeedFile)
		if err != nil {
			return err
		}
		seeds = append(seeds, fromFile...)
	}

	pool := proxy.New(seeds, cfg.MaxFailures, cfg.Cooldown)
	logger.Info("pool initialised", "proxies", pool.Len(), "addr", cfg.Addr)

	srv := &http.Server{
		Addr:              cfg.Addr,
		Handler:           httpapi.New(pool, cfg.APIToken, logger),
		ReadHeaderTimeout: 5 * time.Second,
	}

	errCh := make(chan error, 1)
	go func() {
		if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			errCh <- err
		}
	}()

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	select {
	case err := <-errCh:
		return err
	case <-ctx.Done():
		logger.Info("shutting down")
		shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()
		return srv.Shutdown(shutdownCtx)
	}
}

// readSeedFile reads one proxy URL per line, ignoring blanks and "#" comments.
func readSeedFile(path string) ([]string, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	var out []string
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		out = append(out, line)
	}
	return out, sc.Err()
}
