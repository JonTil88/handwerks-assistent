# Video script — 90 seconds

For the submission to the Symfony AI & MCP Hackathon a **90-second video** is
required (public repository plus video link). This script describes what to
record.

## Recording setup

- Screen recording of the browser (chat window next to a terminal)
- No audio needed — subtitles help during autoplay, but are not mandatory
- Run the commands in advance so no waiting time ends up in the video

## Sequence

| Time | Shot | Action | Text / subtitle |
|---|---|---|---|
| 0:00–0:10 | Terminal | `docker compose up -d` | "Runs completely locally. No API key, no cloud." |
| 0:10–0:30 | Chat | Question: "What does the tiling work in the bathroom cost per square metre?" | Answer appears: "24.50 € per m² (S-1-01), valid from 20.10.2026, source: Supplier quotation L-2026-014" |
| 0:30–0:50 | Chat, mode "Quotation draft" | "Bathroom refurbishment, 12 m² of tiles, replace pipework, install WC" | Draft Q-2026-001 with lines 329.28 € and 76.16 €, total 405.44 € |
| 0:50–1:00 | Chat | "What does it cost to build a passive house?" | "There is no matching position in the service catalogue for this." — **the model is not even asked** |
| 1:00–1:15 | Draft page | Lines with price snapshot per row, then "Approve" | "Approval is a human act — and it is recorded." |
| 1:15–1:25 | Terminal | `php bin/console mcp:server default` + a tool call | "The same domain logic as MCP tools — for any client." |
| 1:25–1:30 | Card | Architecture diagram from the README | "The model does not calculate. It looks things up." + MIT licence |

## The core message

The sentence that has to land in the video:

> **The model does not calculate. It looks things up.**

Everything else is decoration. The three scenes that convince are:

1. **Price question** → answer with date and source (the chain of evidence is visible).
2. **Refusal** → the system honestly says "I don't know" (trust).
3. **Quotation** → lines with price snapshot and a draft that a human releases
   (the division of responsibility).

## Checklist before recording

- [ ] Sample master data seeded, embeddings computed
- [ ] Question 1 and 2 tried once beforehand (the answer is deterministic apart from the wording)
- [ ] The draft in the video is the same one as in the README (otherwise align the numbers)
- [ ] Video uploaded to YouTube as "unlisted" or linked directly at the submission
- [ ] README, licence and architecture diagram are visible before the shot starts
