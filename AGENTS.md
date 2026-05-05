# AGENTS.md — fatespy.com

Cross-tool instructions for any AI agent (Claude Code, ChatGPT/Codex, Gemini/Antigravity, Cursor, Copilot, Windsurf, Cline, Aider).

## Project

**fatespy.com** — mystic intelligence platform: horoscopes, tarot, palmistry, aura reading, numerology. AI-driven readings via multiple LLM APIs (OpenAI, Gemini, Grok). Multi-role authentication (user / admin) with OAuth Google.

- Stack: **Vite + vanilla HTML/CSS frontend + PHP 8.x + MySQL backend**. Shared hosting target.
- Default language: **English**.
- Tone: **premium-mystical** — dark theme, glassmorphism, starry animations.
- Read [handoff_fatespy.md](handoff_fatespy.md) (comprehensive) and [DEV-SERVER.md](DEV-SERVER.md) before substantive work.

## How to work here

- Mobile-first. Astrology / tarot users browse on their phones, often late at night.
- Glass + starry aesthetic is core to the brand — but reading content (horoscopes, tarot interpretations) must stay legible. Solid surfaces under text blocks; glass only for chrome.
- Multi-role auth: user vs admin separation must be clean. No admin endpoints reachable from user-facing JS.
- LLM responses must be cached server-side — don't call OpenAI/Gemini/Grok on every page load.
- PHP + shared hosting = no Composer assumptions for prod-only paths. Test deployment on the actual host.

## Skills

### Global skills (technique — user-level, available across all projects)

| When the task involves… | Read |
|---|---|
| Video — intro / reading-result animations | `C:\Users\Wilhelm\.claude\skills\video-editing\SKILL.md` |
| Liquid glass / glassmorphism / watermorphism (core to brand) | `C:\Users\Wilhelm\.claude\skills\liquid-glass-watermorphism\SKILL.md` |
| Color palette / OKLCH / dark mode / gradients | `C:\Users\Wilhelm\.claude\skills\modern-colors\SKILL.md` (cool dark base — indigo/violet — with a single warm gold accent for premium tier) |
| SEO / AEO / GEO / mobile-first / schema.org (`Article`, `WebApplication`) | `C:\Users\Wilhelm\.claude\skills\seo-aeo-geo-mobile\SKILL.md` |

### Project skills

None yet.

## Hard rules

1. **Mobile-first.** Heavy glass + starfield must still pass mobile performance budgets (LCP < 2.0 s).
2. **Glass on chrome only.** Reading content sits on solid (or near-solid) surfaces.
3. **No admin endpoints in user-facing JS.** Server-side authorization, every endpoint.
4. **Cache LLM responses** by user + reading-type + day. Don't burn API budget on refreshes.
5. **OAuth Google** is the canonical sign-in. Email/password as backup only.
6. **English copy** — concise, evocative, never tacky ("UNLOCK YOUR DESTINY!!!" is a no).
7. **Astrology disclaimers** where required by app store / payment-processor rules — entertainment, not advice.