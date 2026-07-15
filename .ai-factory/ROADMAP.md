# Project Roadmap

> LEADBOT-001 — A Telegram lead-capture bot (name → phone → company) that saves leads to our database, with a Filament admin for managers.

## Context

The current codebase is the Laravel React starter kit on **SQLite**, with **none** of this feature's stack installed yet. The spec (ТЗ v1.0) targets **PostgreSQL + Redis/Horizon + Filament + `defstudio/telegraph` v1 + `propaganistas/laravel-phone`**, so the first milestone is foundational infrastructure. Bot code placement should follow the project's Structured Modules pattern (see `.ai-factory/ARCHITECTURE.md`) — e.g. a `Leads` module — even where the ТЗ shows flat `app/Http/Webhooks` / `app/Services` paths; that is a planning detail for `/aif-plan`.

## Milestones

- [x] **Infrastructure & Dependencies** — Switch DB to MySQL, add Redis + Horizon, install Filament, `defstudio/telegraph` v1, and `propaganistas/laravel-phone`; wire up `.env`. Prerequisite for everything below.
- [ ] **Lead Data Model** — `Lead` model + migration (Telegram source fields, name/phone/phone_raw/company, source, status, meta JSON, indexes), `LeadStatus` enum (new/contacted/qualified/rejected), and a factory.
- [ ] **Lead Domain Service** — `LeadService` with phone normalization/validation to E.164 (UZ `+998` default, KG `+996` fallback) and `updateOrCreate` dedup keyed by `tg_user_id`.
- [ ] **Telegram Conversation Flow (FSM)** — `LeadBotHandler` with `/start` and `/cancel`, the 3-step `name → phone → company` flow, `requestContact` reply keyboard + manual-entry fallback, shared-contact handling, and Redis-backed dialog state.
- [ ] **Telegraph Configuration & Webhook** — storage driver (cache/Redis), security to accept messages from unknown chats, secret-token protection, bot registration, `telegraph:set-webhook`, and BotFather menu commands.
- [ ] **Filament Admin (LeadResource)** — leads table (columns, status badges, status/date/source filters, search by name/phone/company, CSV export, default sort) and a manager-facing edit form.
- [ ] **Security & Anti-spam** — verify webhook secret token, per-user submission throttle, idempotent handling of duplicate update deliveries, and PII-safe logging.
- [ ] **Error Handling & Edge Cases** — `onFailure` recovery, invalid-input reprompts that don't advance state, foreign-contact guard, and abandoned-dialog cleanup (scheduled task).
- [ ] **Automated Tests** — Pest + Telegraph fake covering all acceptance criteria (§14), especially that a shared `requestContact` reaches `handleChatMessage` and parses correctly.
- [ ] **Optional Enhancements (v1.1)** — manager notifications on `LeadCreated`, multi-language dialog (ru/uz/kg), and a pre-save confirmation step. Out of v1 scope.

## Completed

| Milestone | Date |
|-----------|------|
| Infrastructure & Dependencies | 2026-07-15 |
