package httpapi

import (
	"bytes"
	"encoding/json"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/mohamedbelkouri/proxyscrape/proxy-service/internal/proxy"
)

func newServer(seeds ...string) http.Handler {
	pool := proxy.New(seeds, 2, time.Minute)
	return New(pool, "", slog.New(slog.NewTextHandler(io.Discard, nil)))
}

func do(t *testing.T, h http.Handler, method, path, body string) *httptest.ResponseRecorder {
	t.Helper()
	req := httptest.NewRequest(method, path, bytes.NewBufferString(body))
	rec := httptest.NewRecorder()
	h.ServeHTTP(rec, req)
	return rec
}

func TestHealthz(t *testing.T) {
	rec := do(t, newServer("http://a:1"), http.MethodGet, "/healthz", "")
	if rec.Code != http.StatusOK {
		t.Fatalf("status = %d, want 200", rec.Code)
	}
}

func TestNextProxyRotates(t *testing.T) {
	h := newServer("http://a:1", "http://b:2")

	var got []string
	for i := 0; i < 4; i++ {
		rec := do(t, h, http.MethodGet, "/proxy", "")
		if rec.Code != http.StatusOK {
			t.Fatalf("status = %d, want 200", rec.Code)
		}
		var body struct{ Proxy string }
		_ = json.Unmarshal(rec.Body.Bytes(), &body)
		got = append(got, body.Proxy)
	}

	want := []string{"http://a:1", "http://b:2", "http://a:1", "http://b:2"}
	for i := range want {
		if got[i] != want[i] {
			t.Fatalf("got[%d] = %q, want %q", i, got[i], want[i])
		}
	}
}

func TestNextProxyEmptyPool(t *testing.T) {
	rec := do(t, newServer(), http.MethodGet, "/proxy", "")
	if rec.Code != http.StatusServiceUnavailable {
		t.Fatalf("status = %d, want 503", rec.Code)
	}
}

func TestAddProxy(t *testing.T) {
	h := newServer()

	rec := do(t, h, http.MethodPost, "/proxies", `{"proxy":"http://new:8080"}`)
	if rec.Code != http.StatusCreated {
		t.Fatalf("status = %d, want 201", rec.Code)
	}

	rec = do(t, h, http.MethodGet, "/proxies", "")
	var body struct {
		Count int `json:"count"`
	}
	_ = json.Unmarshal(rec.Body.Bytes(), &body)
	if body.Count != 1 {
		t.Fatalf("count = %d, want 1", body.Count)
	}
}

func TestAddProxyRejectsGarbage(t *testing.T) {
	h := newServer()

	if rec := do(t, h, http.MethodPost, "/proxies", `{"proxy":""}`); rec.Code != http.StatusBadRequest {
		t.Fatalf("empty proxy: status = %d, want 400", rec.Code)
	}
	if rec := do(t, h, http.MethodPost, "/proxies", `{"proxy":"not-a-url"}`); rec.Code != http.StatusUnprocessableEntity {
		t.Fatalf("bad url: status = %d, want 422", rec.Code)
	}
}

func TestReportQuarantinesProxy(t *testing.T) {
	h := newServer("http://a:1", "http://b:2")

	for i := 0; i < 2; i++ {
		rec := do(t, h, http.MethodPost, "/proxies/report", `{"proxy":"http://a:1","ok":false}`)
		if rec.Code != http.StatusNoContent {
			t.Fatalf("report: status = %d, want 204", rec.Code)
		}
	}

	for i := 0; i < 3; i++ {
		rec := do(t, h, http.MethodGet, "/proxy", "")
		var body struct{ Proxy string }
		_ = json.Unmarshal(rec.Body.Bytes(), &body)
		if body.Proxy != "http://b:2" {
			t.Fatalf("got %q, want http://b:2 (a is quarantined)", body.Proxy)
		}
	}
}

func TestReportValidation(t *testing.T) {
	h := newServer("http://a:1")
	rec := do(t, h, http.MethodPost, "/proxies/report", `{"proxy":"http://a:1"}`)
	if rec.Code != http.StatusBadRequest {
		t.Fatalf("missing ok: status = %d, want 400", rec.Code)
	}
}

func TestBearerTokenAuth(t *testing.T) {
	pool := proxy.New([]string{"http://a:1"}, 2, time.Minute)
	h := New(pool, "s3cret", slog.New(slog.NewTextHandler(io.Discard, nil)))

	rec := do(t, h, http.MethodGet, "/proxy", "")
	if rec.Code != http.StatusUnauthorized {
		t.Fatalf("no token: status = %d, want 401", rec.Code)
	}

	req := httptest.NewRequest(http.MethodGet, "/proxy", nil)
	req.Header.Set("Authorization", "Bearer s3cret")
	rec = httptest.NewRecorder()
	h.ServeHTTP(rec, req)
	if rec.Code != http.StatusOK {
		t.Fatalf("with token: status = %d, want 200", rec.Code)
	}

	// health check stays open
	if rec := do(t, h, http.MethodGet, "/healthz", ""); rec.Code != http.StatusOK {
		t.Fatalf("healthz should not require auth: status = %d", rec.Code)
	}
}
