// ═══════════════════════════════════════════════════
// FateSpy.com — AI Horoscope Generator (Groq API)
// Uses Llama 3.3 70B via Groq's free tier
// Free: 30 RPM, 14,400 RPD — https://console.groq.com
// ═══════════════════════════════════════════════════

const GROQ_API_KEY = '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';
const GROQ_MODEL = 'llama-3.3-70b-versatile';
const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

// Cache duration per period
const CACHE_DURATIONS = {
    daily: 24 * 60 * 60 * 1000,
    weekly: 7 * 24 * 60 * 60 * 1000,
    monthly: 30 * 24 * 60 * 60 * 1000,
    yearly: 365 * 24 * 60 * 60 * 1000
};

/**
 * Get a date-based cache key for localStorage
 */
function getCacheKey(zodiac, period) {
    const now = new Date();
    let dateKey;
    if (period === 'daily') {
        dateKey = now.toISOString().split('T')[0];
    } else if (period === 'weekly') {
        const startOfYear = new Date(now.getFullYear(), 0, 1);
        const weekNum = Math.ceil((((now - startOfYear) / 86400000) + startOfYear.getDay() + 1) / 7);
        dateKey = `${now.getFullYear()}-W${weekNum}`;
    } else if (period === 'monthly') {
        dateKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    } else {
        dateKey = `${now.getFullYear()}`;
    }
    return `fatespy_ai_${zodiac}_${period}_${dateKey}`;
}

/**
 * Check localStorage cache
 */
function getCachedReading(zodiac, period) {
    try {
        const key = getCacheKey(zodiac, period);
        const cached = localStorage.getItem(key);
        if (cached) {
            const data = JSON.parse(cached);
            const age = Date.now() - data.timestamp;
            if (age < (CACHE_DURATIONS[period] || CACHE_DURATIONS.daily)) {
                return data.readings;
            }
            localStorage.removeItem(key);
        }
    } catch (e) {
        console.warn('Cache read error:', e);
    }
    return null;
}

/**
 * Save to localStorage cache
 */
function cacheReading(key, readings) {
    try {
        localStorage.setItem(key, JSON.stringify({
            timestamp: Date.now(),
            readings
        }));
    } catch (e) {
        console.warn('Cache write error:', e);
    }
}

/**
 * Call Groq API (OpenAI-compatible)
 */
async function callGroq(systemPrompt, userPrompt) {
    if (!GROQ_API_KEY) {
        console.warn('FateSpy AI: No Groq API key configured. Using fallback texts.');
        return null;
    }

    try {
        const response = await fetch(GROQ_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${GROQ_API_KEY}`
            },
            body: JSON.stringify({
                model: GROQ_MODEL,
                messages: [
                    { role: 'system', content: systemPrompt },
                    { role: 'user', content: userPrompt }
                ],
                temperature: 0.9,
                max_tokens: 1024,
                response_format: { type: 'json_object' }
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Groq API error:', response.status, errorText);
            return null;
        }

        const data = await response.json();
        const text = data?.choices?.[0]?.message?.content;

        if (!text) {
            console.error('Groq returned empty response');
            return null;
        }

        const parsed = JSON.parse(text);
        if (parsed.love && parsed.career && parsed.money && parsed.health && parsed.home) {
            return parsed;
        }

        console.error('Groq response missing required fields:', parsed);
        return null;

    } catch (error) {
        console.error('Groq API call failed:', error);
        return null;
    }
}

// ── System prompt (shared) ──
const SYSTEM_PROMPT = `You are FateSpy, a world-renowned professional astrologer with deep knowledge of celestial mechanics, planetary transits, and zodiac personality archetypes. You provide warm, mystical yet practical horoscope readings that feel personal and insightful. Always respond with valid JSON only.`;

/**
 * Generate AI horoscope reading with caching and fallback
 */
export async function generateHoroscopeReading(zodiac, period) {
    // 1. Check cache
    const cached = getCachedReading(zodiac, period);
    if (cached) {
        console.log(`FateSpy AI: Cached ${period} reading for ${zodiac}`);
        return cached;
    }

    // 2. Build prompt
    const zodiacName = zodiac.charAt(0).toUpperCase() + zodiac.slice(1);
    const periodLabel = period.charAt(0).toUpperCase() + period.slice(1);
    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    const userPrompt = `Today is ${today}. Generate a ${periodLabel.toLowerCase()} horoscope for ${zodiacName}. Write 2-3 insightful sentences per category, specific to ${zodiacName}'s personality and current planetary positions.

Respond ONLY with this JSON format:
{
  "love": "...",
  "career": "...",
  "money": "...",
  "health": "...",
  "home": "..."
}`;

    // 3. Call Groq
    const result = await callGroq(SYSTEM_PROMPT, userPrompt);

    if (result) {
        cacheReading(getCacheKey(zodiac, period), result);
        console.log(`FateSpy AI: Fresh ${period} reading for ${zodiac}`);
        return result;
    }

    return null; // caller uses fallback
}

/**
 * Generate AI zodiac traits with caching and fallback
 */
export async function generateZodiacTraits(zodiac) {
    const cacheKey = `fatespy_traits_${zodiac}`;

    // Check cache (30 day validity)
    try {
        const cached = localStorage.getItem(cacheKey);
        if (cached) {
            const data = JSON.parse(cached);
            if (Date.now() - data.timestamp < 30 * 24 * 60 * 60 * 1000) {
                console.log(`FateSpy AI: Cached traits for ${zodiac}`);
                return data.readings;
            }
        }
    } catch (e) { /* ignore */ }

    const zodiacName = zodiac.charAt(0).toUpperCase() + zodiac.slice(1);

    const userPrompt = `Write a deep personality profile for ${zodiacName} across 5 life categories. 2-3 rich sentences per category about how ${zodiacName} naturally behaves.

Respond ONLY with this JSON format:
{
  "love": "...",
  "career": "...",
  "money": "...",
  "health": "...",
  "home": "..."
}`;

    const result = await callGroq(SYSTEM_PROMPT, userPrompt);

    if (result) {
        cacheReading(cacheKey, result);
        console.log(`FateSpy AI: Fresh traits for ${zodiac}`);
        return result;
    }

    return null;
}

/**
 * Check if AI is configured
 */
export function isAIAvailable() {
    return !!GROQ_API_KEY;
}
