// Package httpapi exposes the proxy Pool over a small JSON HTTP API.
//
//	GET  /healthz          -> 200 "ok"
//	GET  /proxy            -> { "proxy": "http://host:port" }        (next in rotation)
//	GET  /proxies          -> { "count": N, "proxies": [ {url, healthy, failures}, ... ] }
//	POST /proxies          -> { "proxy": "http://host:port" }        (add to the pool)
//	POST /proxies/report   -> { "proxy": "...", "ok": true|false }   (report an outcome)
package httpapi

import (
	"encoding/json"
	"io"
	"log/slog"
	"net/http"
	"time"

	"github.com/mohamedbelkouri/proxyscrape/proxy-service/internal/proxy"
)

// Pool is the subset of *proxy.Pool the API needs (kept small for testing).
type Pool interface {
	Next() (string, error)
	Add(raw string) error
	Report(raw string, ok bool)
	Snapshot() []proxy.Stats
	Len() int
}

// New returns an http.Handler wired to the given pool.
func New(pool Pool, token string, logger *slog.Logger) http.Handler {
	h := &handler{pool: pool, token: token, log: logger}

	mux := http.NewServeMux()
	mux.HandleFunc("GET /healthz", h.health)
	mux.HandleFunc("GET /proxy", h.auth(h.nextProxy))
	mux.HandleFunc("GET /proxies", h.auth(h.listProxies))
	mux.HandleFunc("POST /proxies", h.auth(h.addProxy))
	mux.HandleFunc("POST /proxies/report", h.auth(h.reportProxy))

	return logRequests(logger, mux)
}

type handler struct {
	pool  Pool
	token string
	log   *slog.Logger
}

func (h *handler) health(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{"status": "ok", "proxies": h.pool.Len()})
}

func (h *handler) nextProxy(w http.ResponseWriter, _ *http.Request) {
	p, err := h.pool.Next()
	if err != nil {
		writeError(w, http.StatusServiceUnavailable, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]string{"proxy": p})
}

func (h *handler) listProxies(w http.ResponseWriter, _ *http.Request) {
	stats := h.pool.Snapshot()
	writeJSON(w, http.StatusOK, map[string]any{"count": len(stats), "proxies": stats})
}

func (h *handler) addProxy(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Proxy string `json:"proxy"`
	}
	if err := decode(r, &body); err != nil || body.Proxy == "" {
		writeError(w, http.StatusBadRequest, "body must be {\"proxy\": \"http://host:port\"}")
		return
	}
	if err := h.pool.Add(body.Proxy); err != nil {
		writeError(w, http.StatusUnprocessableEntity, err.Error())
		return
	}
	writeJSON(w, http.StatusCreated, map[string]string{"proxy": body.Proxy})
}

func (h *handler) reportProxy(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Proxy string `json:"proxy"`
		OK    *bool  `json:"ok"`
	}
	if err := decode(r, &body); err != nil || body.Proxy == "" || body.OK == nil {
		writeError(w, http.StatusBadRequest, "body must be {\"proxy\": \"...\", \"ok\": true|false}")
		return
	}
	h.pool.Report(body.Proxy, *body.OK)
	w.WriteHeader(http.StatusNoContent)
}

// auth wraps a handler with optional bearer-token checking.
func (h *handler) auth(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if h.token != "" && r.Header.Get("Authorization") != "Bearer "+h.token {
			writeError(w, http.StatusUnauthorized, "missing or invalid bearer token")
			return
		}
		next(w, r)
	}
}

func decode(r *http.Request, v any) error {
	defer r.Body.Close()
	dec := json.NewDecoder(io.LimitReader(r.Body, 1<<16))
	dec.DisallowUnknownFields()
	return dec.Decode(v)
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func writeError(w http.ResponseWriter, status int, msg string) {
	writeJSON(w, status, map[string]string{"error": msg})
}

func logRequests(logger *slog.Logger, next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		sw := &statusWriter{ResponseWriter: w, status: http.StatusOK}
		next.ServeHTTP(sw, r)
		logger.Info("request",
			"method", r.Method,
			"path", r.URL.Path,
			"status", sw.status,
			"duration_ms", time.Since(start).Milliseconds(),
		)
	})
}

type statusWriter struct {
	http.ResponseWriter
	status int
}

func (s *statusWriter) WriteHeader(code int) {
	s.status = code
	s.ResponseWriter.WriteHeader(code)
}
