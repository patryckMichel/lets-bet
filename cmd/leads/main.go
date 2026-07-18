package main

import (
	"database/sql"
	"encoding/json"
	"errors"
	"log"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/lib/pq"
)

type leadRequest struct {
	Name      string `json:"name"`
	Whatsapp  string `json:"whatsapp"`
	Instagram string `json:"instagram"`
	Source    string `json:"source"`
	UserAgent string `json:"userAgent"`
}

func main() {
	dsn := os.Getenv("LEADS_DATABASE_URL")
	if dsn == "" {
		dsn = "postgres://postgres:postgres@217.196.60.187:32769/bet369?sslmode=disable"
	}

	addr := os.Getenv("LEADS_ADDR")
	if addr == "" {
		addr = "127.0.0.1:8089"
	}

	db, err := sql.Open("postgres", dsn)
	if err != nil {
		log.Fatal(err)
	}
	defer db.Close()

	db.SetMaxOpenConns(10)
	db.SetConnMaxLifetime(5 * time.Minute)

	if err := db.Ping(); err != nil {
		log.Fatalf("db ping failed: %v", err)
	}

	if err := ensureSchema(db); err != nil {
		log.Fatalf("schema failed: %v", err)
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, r *http.Request) {
		writeJSON(w, http.StatusOK, map[string]any{"ok": true, "service": "leads"})
	})

	mux.HandleFunc("/api/leads/check", func(w http.ResponseWriter, r *http.Request) {
		withCORS(w, r)
		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusNoContent)
			return
		}
		if r.Method != http.MethodGet && r.Method != http.MethodPost {
			writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"ok": false, "error": "method not allowed"})
			return
		}

		whatsapp := ""
		if r.Method == http.MethodGet {
			whatsapp = digitsOnly(r.URL.Query().Get("whatsapp"))
		} else {
			var body struct {
				Whatsapp string `json:"whatsapp"`
			}
			if err := json.NewDecoder(http.MaxBytesReader(w, r.Body, 1<<20)).Decode(&body); err != nil {
				writeJSON(w, http.StatusBadRequest, map[string]any{"ok": false, "error": "invalid json"})
				return
			}
			whatsapp = digitsOnly(body.Whatsapp)
		}

		if len(whatsapp) < 10 {
			writeJSON(w, http.StatusBadRequest, map[string]any{"ok": false, "error": "whatsapp invalid"})
			return
		}

		exists, err := phoneExists(db, whatsapp)
		if err != nil {
			log.Printf("check lead failed: %v", err)
			writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "error": "check failed"})
			return
		}

		writeJSON(w, http.StatusOK, map[string]any{"ok": true, "exists": exists})
	})

	mux.HandleFunc("/api/leads", func(w http.ResponseWriter, r *http.Request) {
		withCORS(w, r)
		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusNoContent)
			return
		}
		if r.Method != http.MethodPost {
			writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"ok": false, "error": "method not allowed"})
			return
		}

		var req leadRequest
		dec := json.NewDecoder(http.MaxBytesReader(w, r.Body, 1<<20))
		if err := dec.Decode(&req); err != nil {
			writeJSON(w, http.StatusBadRequest, map[string]any{"ok": false, "error": "invalid json"})
			return
		}

		req.Name = strings.TrimSpace(req.Name)
		req.Whatsapp = digitsOnly(req.Whatsapp)
		req.Instagram = strings.TrimSpace(req.Instagram)
		req.Source = strings.TrimSpace(req.Source)
		if req.Source == "" {
			req.Source = "landing"
		}
		if req.UserAgent == "" {
			req.UserAgent = r.UserAgent()
		}

		if req.Name == "" || len(req.Whatsapp) < 10 {
			writeJSON(w, http.StatusBadRequest, map[string]any{"ok": false, "error": "name and whatsapp required"})
			return
		}

		exists, err := phoneExists(db, req.Whatsapp)
		if err != nil {
			log.Printf("precheck lead failed: %v", err)
			writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "error": "failed to save"})
			return
		}
		if exists {
			writeJSON(w, http.StatusConflict, map[string]any{
				"ok":     false,
				"exists": true,
				"error":  "whatsapp already registered",
				"code":   "PHONE_EXISTS",
			})
			return
		}

		_, err = db.Exec(`
			INSERT INTO landing_leads (name, whatsapp, instagram, source, user_agent, created_at)
			VALUES ($1, $2, $3, $4, $5, NOW())
		`, req.Name, req.Whatsapp, nullIfEmpty(req.Instagram), req.Source, req.UserAgent)
		if err != nil {
			var pqErr *pq.Error
			if errors.As(err, &pqErr) && pqErr.Code == "23505" {
				writeJSON(w, http.StatusConflict, map[string]any{
					"ok":     false,
					"exists": true,
					"error":  "whatsapp already registered",
					"code":   "PHONE_EXISTS",
				})
				return
			}
			log.Printf("insert lead failed: %v", err)
			writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "error": "failed to save"})
			return
		}

		writeJSON(w, http.StatusCreated, map[string]any{"ok": true, "exists": false})
	})

	adminToken := strings.TrimSpace(os.Getenv("LEADS_ADMIN_TOKEN"))
	if adminToken == "" {
		adminToken = "LestBet369Admin"
	}

	mux.HandleFunc("/api/admin/leads", func(w http.ResponseWriter, r *http.Request) {
		withCORS(w, r)
		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusNoContent)
			return
		}
		if r.Method != http.MethodGet {
			writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"ok": false, "error": "method not allowed"})
			return
		}
		token := strings.TrimSpace(r.Header.Get("X-Admin-Token"))
		if token == "" {
			token = strings.TrimSpace(r.URL.Query().Get("token"))
		}
		if token == "" || token != adminToken {
			writeJSON(w, http.StatusUnauthorized, map[string]any{"ok": false, "error": "unauthorized"})
			return
		}

		rows, err := db.Query(`
			SELECT id, name, whatsapp, COALESCE(instagram, ''), source, created_at
			FROM landing_leads
			ORDER BY created_at DESC
			LIMIT 500
		`)
		if err != nil {
			log.Printf("admin list leads failed: %v", err)
			writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "error": "failed to list"})
			return
		}
		defer rows.Close()

		leads := make([]map[string]any, 0)
		for rows.Next() {
			var (
				id        int64
				name      string
				whatsapp  string
				instagram string
				source    string
				createdAt time.Time
			)
			if err := rows.Scan(&id, &name, &whatsapp, &instagram, &source, &createdAt); err != nil {
				log.Printf("admin scan lead failed: %v", err)
				writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "error": "failed to list"})
				return
			}
			leads = append(leads, map[string]any{
				"id":         id,
				"name":       name,
				"whatsapp":   whatsapp,
				"instagram":  instagram,
				"source":     source,
				"created_at": createdAt.Format(time.RFC3339),
			})
		}
		if err := rows.Err(); err != nil {
			writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "error": "failed to list"})
			return
		}

		writeJSON(w, http.StatusOK, map[string]any{"ok": true, "leads": leads, "count": len(leads)})
	})

	log.Printf("leads api listening on %s (admin token configured)", addr)
	log.Fatal(http.ListenAndServe(addr, mux))
}

func phoneExists(db *sql.DB, whatsapp string) (bool, error) {
	variants := phoneVariants(whatsapp)
	var exists bool
	err := db.QueryRow(`
		SELECT EXISTS (
			SELECT 1 FROM landing_leads
			WHERE whatsapp = ANY($1)
		)
	`, pq.Array(variants)).Scan(&exists)
	return exists, err
}

func phoneVariants(whatsapp string) []string {
	w := digitsOnly(whatsapp)
	set := map[string]struct{}{w: {}}
	if strings.HasPrefix(w, "55") && len(w) > 12 {
		set[strings.TrimPrefix(w, "55")] = struct{}{}
	} else if len(w) >= 10 && len(w) <= 11 {
		set["55"+w] = struct{}{}
	}
	out := make([]string, 0, len(set))
	for k := range set {
		out = append(out, k)
	}
	return out
}

func ensureSchema(db *sql.DB) error {
	_, err := db.Exec(`
		CREATE TABLE IF NOT EXISTS landing_leads (
			id BIGSERIAL PRIMARY KEY,
			name TEXT NOT NULL,
			whatsapp TEXT NOT NULL,
			instagram TEXT NULL,
			source TEXT NOT NULL DEFAULT 'landing',
			user_agent TEXT NULL,
			created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
		);
		CREATE INDEX IF NOT EXISTS idx_landing_leads_created_at ON landing_leads (created_at DESC);
		CREATE INDEX IF NOT EXISTS idx_landing_leads_whatsapp ON landing_leads (whatsapp);
	`)
	if err != nil {
		return err
	}

	// Keep newest row per phone so the unique index can be created.
	_, err = db.Exec(`
		DELETE FROM landing_leads a
		USING landing_leads b
		WHERE a.whatsapp = b.whatsapp AND a.id < b.id
	`)
	if err != nil {
		return err
	}

	_, err = db.Exec(`CREATE UNIQUE INDEX IF NOT EXISTS uq_landing_leads_whatsapp ON landing_leads (whatsapp)`)
	return err
}

func withCORS(w http.ResponseWriter, r *http.Request) {
	origin := r.Header.Get("Origin")
	allowed := map[string]bool{
		"https://lestber369.com":     true,
		"https://www.lestber369.com": true,
		"http://127.0.0.1:5500":      true,
		"http://localhost:5500":      true,
	}
	if allowed[origin] {
		w.Header().Set("Access-Control-Allow-Origin", origin)
	} else if origin == "" {
		w.Header().Set("Access-Control-Allow-Origin", "*")
	}
	w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
	w.Header().Set("Access-Control-Allow-Headers", "Content-Type, X-Admin-Token")
	w.Header().Set("Vary", "Origin")
}

func writeJSON(w http.ResponseWriter, status int, payload map[string]any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func digitsOnly(v string) string {
	var b strings.Builder
	for _, r := range v {
		if r >= '0' && r <= '9' {
			b.WriteRune(r)
		}
	}
	return b.String()
}

func nullIfEmpty(v string) any {
	if strings.TrimSpace(v) == "" {
		return nil
	}
	return v
}
