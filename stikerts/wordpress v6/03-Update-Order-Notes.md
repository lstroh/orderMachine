# Update — Order Notes (Threaded Log)

*Package 5 · Schema in `02-Update-Data-Model.md` §A. Self-contained.*

---

## 1. What this adds

An **admin-only threaded log** of free-text notes on an order so operators can record production issues, customer follow-ups, packing quirks, etc.

Each entry stores:

- Who (`user_id` → WP display name in UI)
- When (`created_at`)
- Body (plain text)

## 2. Settled rules

| Topic | Decision |
|---|---|
| Shape | Threaded log (many entries), not a single editable blob |
| Surfaces | **Order detail only** |
| Visibility | Admin (`manage_options`) only |
| Channels / REST / MCP | Not exposed in v1 |
| Board / list | No snippets in v1 |

## 3. Behaviour

### Create

- Form at bottom (or side) of order detail: textarea + “Add note”.
- Reject empty / whitespace-only bodies.
- Soft max length in UI (recommend **5000** chars) — hard max **open item**.

### Display

- Chronological list of notes for that `order_id`.
- Show author display name (fallback: user login / “User #id” if missing).
- Preserve newlines (`white-space: pre-wrap`).
- Newest at bottom (chat-style) — **open item** if you prefer newest first.

### Edit / delete

**Recommendation for v1:** append-only (no edit, no delete) for a clean audit trail.  
**Open item** if you want edit/delete own notes.

### Permissions

Same capability as other Order Machine admin screens (`manage_options`). No per-note ACL.

## 4. UI requirements

| Page | Purpose |
|---|---|
| Order detail | Notes thread + add form |
| Orders list / Board | None (v1) |

## 5. Out of scope

- Mentions / @notifications
- Attachments / images
- Notes synced to eBay/Etsy messages
- Customer-visible notes
- REST/MCP read or write
- Filtering orders “with notes”

## 6. Open items

1. Hard max length for `body`.
2. Newest-first vs oldest-first display.
3. Append-only vs edit/delete (own only / any admin).
4. Show note count badge on orders list later? (recommend no in v1)
