-- Leads da landing LESTBET 369
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
