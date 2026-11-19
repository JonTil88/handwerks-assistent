# Craft Assistant

A price and quotation assistant for trade businesses, built with **Symfony 8**,
**Symfony AI 1.0.0-BETA1** and the **official PHP MCP SDK** (`mcp/sdk`).

The guiding idea in one sentence:

> **The model does not calculate. It looks things up.**
> Every figure in the system is computed from the master data of the business —
> the model only assigns and phrases. What is not backed by data is not output.

That makes an assistant in the trades finally verifiable: every price carries its
validity date and its source, every calculation can be re-done by hand, and
missing data leads to an honest refusal instead of an invented number.

---

## Contents

- [What the project is about](#what-the-project-is-about)
- [Quick start](#quick-start)
- [Architecture](#architecture)
- [The chain of evidence](#the-chain-of-evidence)
- [MCP server](#mcp-server)
- [Regression set](#regression-set)
- [What we learned while building it](#what-we-learned-while-building-it)
- [Running without Docker](#running-without-docker)
- [Limits](#limits)
- [License](#license)

---

## What the project is about

| Building block | Description |
|---|---|
| **Master data** | Service catalogue with keywords, materials with price snapshots, wage rates, subcontractors, machine rates — each with a validity date and a source |
| **Deterministic calculation** | Labour, material, subcontractor, machine, disposal, composite price, individually configurable markups — computed in PHP, without a model, without floating point (`Money` as integer cents) |
| **Hybrid search** | Word matches (text, keywords, default material) plus pgvector similarity for linguistic variants |
| **Question mode** | Question → retrieval → context → model → citation check. If the retrieval finds nothing, the model is not asked at all |
| **Quotation mode** | Order → assignment by the model → calculation by the calculator → **draft**, which a human has to approve |
| **Append-only audit log** | Every creation and every approval is written once and stays |
| **MCP server** | The same domain logic as six tools for any MCP-capable client (Claude Code, Claude Desktop, IDE) — over HTTP and STDIO |
| **Regression set** | `app:eval` checks hit rate, citation rate, decline rate and the **hallucination rate** — which has to be zero |

---

## Quick start

Prerequisite: Docker with Compose. Everything runs locally — **no API key, no
cloud, no data leaving the machine**.

```bash
git clone https://github.com/JonTil88/handwerks-assistent.git
cd handwerks-assistent

# 1. Models (once, ~5 GB)
docker compose up -d ollama
docker compose exec ollama ollama pull qwen3:8b
docker compose exec ollama ollama pull nomic-embed-text

# 2. Database, schema and sample master data
docker compose up -d db
docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm app php bin/console app:seed --no-interaction --force

# 3. Compute the embeddings (pgvector)
docker compose run --rm app php bin/console app:index

# 4. Start
docker compose up -d app
```

The application is then available at <http://localhost:8000>, the MCP endpoint at
<http://localhost:8000/mcp>.

**If Ollama already runs on your machine:** point `OLLAMA_MODELS_DIR` in `.env` at
the model directory (e.g. `/var/lib/ollama`) and the models are not downloaded again.

```bash
# Run the regression set
docker compose run --rm app php bin/console app:eval

# Show the available MCP tools
docker compose run --rm app php bin/console debug:mcp
```

---

## Architecture

```mermaid
flowchart LR
    subgraph Operation
        Chat[Chat interface]
        MCP[MCP server<br/>6 tools]
    end

    subgraph AI
        Chat2[ChatClient<br/>Symfony AI / Ollama]
        Ret[Vectorizer<br/>nomic-embed-text]
    end

    subgraph Domain logic
        S[ServiceSearcher<br/>hybrid]
        A[QueryAnswerer]
        B[QuotationAssistant]
        K[Calculator<br/>deterministic]
        R[MasterDataResolver<br/>snapshot + source]
    end

    subgraph Data
        PG[(PostgreSQL 17<br/>+ pgvector)]
    end

    Chat --> A
    A --> S --> PG
    A --> Ret --> PG
    A --> Chat2
    B --> S
    B --> Chat2
    B --> K --> R --> PG
    MCP --> S
    MCP --> K
    MCP --> PG
```

The decisive separation: **`src/Domain/` knows nothing about AI.** Calculator,
MasterDataResolver and `Money` are plain PHP and testable without a model. The AI
layer `src/Ai/` sits on top and may only assign and phrase.

```
src/
├─ Domain/          Calculator · MasterDataResolver · Money · PriceSnapshot   ← no AI
├─ Entity/          Doctrine entities
├─ Repository/      Doctrine repositories (incl. pgvector search)
├─ Retrieval/       Vectorizer · EmbeddingStore · ServiceSearcher
├─ Ai/              ChatClient · QueryAnswerer · QuotationAssistant · QuotationParser · CitationChecker
├─ Mcp/Tools/       six MCP tools
├─ Controller/      Chat and quotation draft
└─ Command/         app:seed · app:index · app:eval
```

The vectors live in the table `document_embedding` — deliberately outside the ORM,
because pgvector is a PostgreSQL extension without a Doctrine type.

---

## The chain of evidence

Every price in the system carries three attributes: **amount, date it is valid
from, and where it came from.**

```
Heat exchanger 24 kW   1.249,00 € per unit   valid from 29.10.2026 · Supplier quotation L-2026-021
```

The resolver returns the snapshot valid as of the given date. If there is none,
it returns `null` — **no estimated value**. The calculator then marks the line as
"cannot be calculated" and names the reason.

The prompt forbids the model to calculate and requires it to name the origin of
every figure. In addition, the `CitationChecker` compares every identifier in the
answer with the ones actually delivered in the context. An unbacked number does
not slip into the answer unnoticed, but appears as a check note:

> ⚠ The answer names "S-9-99" — this identifier is not part of the master data that was provided.

Approving a quotation draft is a **human act**: the model may create a draft, but
never approve one. The approval is recorded in the audit log.

---

## MCP server

The MCP bundle exposes the same domain logic as tools — that way the business can
be operated from any MCP-capable client.

| Tool | Purpose |
|---|---|
| `find_services` | Search the service catalogue, including the price snapshot |
| `get_price_snapshot` | The price snapshot valid for a material, with date and source |
| `calculate_quotation` | Calculate quotation lines deterministically (JSON input) |
| `create_quotation_draft` | Create a draft from an order text |
| `approve_quotation_draft` | Approve a draft (a human act) |
| `read_audit_log` | Read the last entries of the audit log |

**HTTP transport:** `POST http://localhost:8000/mcp` (streamable HTTP)

**STDIO transport** for local clients:

```bash
docker compose exec app php bin/console mcp:server default
```

Example call (`calculate_quotation`, real response):

```json
{
  "jsonrpc": "2.0", "id": 3, "method": "tools/call",
  "params": {
    "name": "calculate_quotation",
    "arguments": {
      "lines_json": "[{\"service_number\":\"S-1-01\",\"quantity\":12},{\"service_number\":\"S-1-05\",\"quantity\":1}]",
      "as_of": "2026-11-18"
    }
  }
}
```

```json
{
  "line_count": 2,
  "lines": [
    {
      "service_number": "S-1-01",
      "quantity": "12.000",
      "material_cost": "294.00",
      "markup_percent": "12.00",
      "total_net": "329.28",
      "price_snapshot": "Ceramic tile 30x60 white 24.50 € per m² (20.10.2026 · Supplier quotation L-2026-014)"
    },
    {
      "service_number": "S-1-05",
      "labour_cost": "68.00",
      "total_net": "76.16",
      "price_snapshot": "Labour Skilled worker 42.50 €/h (valid from 18.05.2026)"
    }
  ],
  "grand_total": "405,44",
  "warnings": []
}
```

---

## Regression set

`app:eval` answers 13 questions against the sample master data and checks four
figures. Measurement of 19.11.2026, locally on an RTX 3090 with `qwen3:8b`:

| Figure | Target | Measured |
|---|---|---|
| Hit rate (search) — does the search find the right service? | ≥ 90 % | **100 % (10/10)** |
| Citation rate (answer) — does the answer name that service? | ≥ 90 % | **100 % (10/10)** |
| Decline rate — does it decline when there is no data? | 100 % | **100 % (3/3)** |
| **Hallucination rate** — does it name identifiers that do not exist? | **0** | **0** |

The citation rate is the stricter of the two access figures: it checks whether the
answer **names** the right service — not only whether the search found it. That is
the figure that backs the core promise: every number can be traced back to a
position and a price snapshot.

The decline rate is the real test: for questions about a passive house, a 30-tonne
crane or a salary, the system does **not** ask the model, it answers directly that
it has no data. That is cheaper, faster and more honest — and exactly the
behaviour a business with a GoBD audit needs.

Price snapshots also carry a flag for stale prices: if a price is older than 365
days, the system points that out instead of using it silently (demonstrable in the
sample data with "Aluminium edge trim 8 mm").

---

## What we learned while building it

The most important finding from the regression set — and the reason the search is
built the way it is:

> **Vector similarity alone cannot separate "fits" from "does not fit".**

Measured with `nomic-embed-text` across all 31 services:

| Question | Best match | Score |
|---|---|---|
| "What does the tiling work in the bathroom cost?" | S-1-01 Lay ceramic tiles | 0.638 |
| "What does it cost to build a passive house?" | S-2-05 Check safety valve … | 0.633 |

Five thousandths of a difference — no threshold can decide relevance on that
basis. The remedy is the **word match** over service text, keywords and default
material (with composite handling: "tiling work" finds "lay tiles", because
"tiles" is a prefix of the query word).

Only with that combination does the system separate:

| | Word match | Vector |
|---|---|---|
| "What does it cost to install a washout WC?" | S-1-05 (keyword "washout") | 0.664 |
| "Lay the cables, electrical" | S-3-02 | 0.810 |
| "Building a passive house" | — | 0.633 → **discarded** |

The consequence for the business: **keywords are not decoration, they are the
assignment.** A service catalogue without maintained keywords is useless with an
embedding model alone.

---

## Running without Docker

```bash
composer install
export DATABASE_URL="postgresql://handwerks:handwerks@127.0.0.1:5432/handwerks"
export OLLAMA_BASE_URL="http://127.0.0.1:11434"

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed --no-interaction --force
php bin/console app:index
php -S 127.0.0.1:8000 -t public
```

Prerequisite is a PostgreSQL with the `vector` extension and a local Ollama with
`qwen3:8b` (chat) and `nomic-embed-text` (embeddings). Both models run locally —
no data is transferred to third parties.

---

## Limits

An honest account of what this project **is not**:

- **Not an ERP.** It is the verifiable AI and calculation layer that plugs into a
  business application — master data maintenance, appointments, invoices and the
  document archive of a real trade business are not included.
- **GoBD compliance is a professional review.** The system provides the technical
  basis (immutable prices with date and source, audit log, deletion concept for
  the domain). The confirmation itself belongs to the review by a tax adviser or
  auditor.
- **The search runs in memory.** For 31 services that is fine. A real application
  needs a full-text index (Postgres `tsvector` or Elasticsearch) — the word match
  is deliberately built as a replaceable component.
- **Answer quality follows the model.** With an 8B model the assignment works
  well, but not flawlessly. That is exactly why the model does not calculate and
  why there is a regression set.
- **Embeddings run through Ollama's OpenAI-compatible interface**, because the
  Symfony AI Ollama bridge in 1.0.0-BETA1 carries no embedding models in its own
  catalogue. Both are local, but it is two paths instead of one.
- **An answer takes 10–20 seconds** on the GPU (local 8B model, cold start after
  every restart). For the business the answer quality counts, not the latency —
  for a chat in the office a smaller model would be the right choice.
- **No login yet.** The business runs in anonymous mode; approving a quotation
  draft is bound to the session by a CSRF token, but there are no user accounts
  yet. For productive use, users, roles and an approval rule belong on top — the
  technical basis (security bundle, CSRF, audit log with user identity) is in place.
- **Approval still needs its own permission.** Today everyone who opens the draft
  page may approve; the name is recorded but not checked.

---

## License

MIT — see [LICENSE](LICENSE). All data in the sample master data is synthetic and
invented; these are not real prices, customers or documents.
