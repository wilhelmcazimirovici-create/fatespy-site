#!/usr/bin/env node
// ═══════════════════════════════════════════════════
// FateSpy.com — Daily Horoscope Generator (Groq AI)
// Run this script daily via cron/scheduler to pre-generate
// all horoscope readings for all 12 signs × 4 periods.
// Output: public/data/horoscopes.json
// ═══════════════════════════════════════════════════
// Usage: node scripts/generate-horoscopes.js
// Cron:  0 6 * * * cd /path/to/fatespy.com && node scripts/generate-horoscopes.js

const GROQ_API_KEY = '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';
const GROQ_MODEL = 'llama-3.3-70b-versatile';
const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

const fs = require('fs');
const path = require('path');

const ZODIAC_SIGNS = [
    'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo',
    'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'
];
const PERIODS = ['daily', 'weekly', 'monthly', 'yearly'];

const SYSTEM_PROMPT = `You are FateSpy, a world-renowned professional astrologer with deep knowledge of celestial mechanics, planetary transits, and zodiac personality archetypes. You provide warm, mystical yet practical horoscope readings that feel personal, insightful, and specific. Always respond with valid JSON only, no markdown, no code fences.`;

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function callGroq(userPrompt, retries = 3) {
    for (let attempt = 1; attempt <= retries; attempt++) {
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
                        { role: 'system', content: SYSTEM_PROMPT },
                        { role: 'user', content: userPrompt }
                    ],
                    temperature: 0.9,
                    max_tokens: 1024,
                    response_format: { type: 'json_object' }
                })
            });

            if (response.status === 429) {
                const wait = attempt * 5000;
                console.log(`  ⏳ Rate limited, waiting ${wait / 1000}s...`);
                await sleep(wait);
                continue;
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error(`  ❌ API error ${response.status}: ${errorText}`);
                if (attempt < retries) {
                    await sleep(2000);
                    continue;
                }
                return null;
            }

            const data = await response.json();
            const text = data?.choices?.[0]?.message?.content;
            if (!text) return null;

            const parsed = JSON.parse(text);
            if (parsed.love && parsed.career && parsed.money && parsed.health && parsed.home) {
                return parsed;
            }
            console.error('  ⚠️ Missing fields in response');
            return null;

        } catch (error) {
            console.error(`  ❌ Attempt ${attempt} failed:`, error.message);
            if (attempt < retries) await sleep(2000);
        }
    }
    return null;
}

function buildPrompt(zodiac, period) {
    const zodiacName = zodiac.charAt(0).toUpperCase() + zodiac.slice(1);
    const periodLabel = period.charAt(0).toUpperCase() + period.slice(1);
    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    return `Today is ${today}. Generate a ${periodLabel.toLowerCase()} horoscope for ${zodiacName}.

Write 2-3 insightful, specific sentences per category. Make them feel personal to ${zodiacName}'s personality traits and current planetary positions. Be encouraging but honest about challenges.

Respond ONLY with this JSON:
{
  "love": "2-3 sentences about love & relationships",
  "career": "2-3 sentences about career & work",
  "money": "2-3 sentences about money & finances",
  "health": "2-3 sentences about health & energy",
  "home": "2-3 sentences about family & home"
}`;
}

async function main() {
    console.log('🔮 FateSpy Horoscope Generator');
    console.log('══════════════════════════════');
    console.log(`📅 ${new Date().toLocaleString()}`);
    console.log(`🤖 Model: ${GROQ_MODEL}`);
    console.log(`📊 Generating: ${ZODIAC_SIGNS.length} signs × ${PERIODS.length} periods = ${ZODIAC_SIGNS.length * PERIODS.length} readings\n`);

    const output = {
        generated_at: new Date().toISOString(),
        model: GROQ_MODEL,
        readings: {}
    };

    let success = 0;
    let failed = 0;

    for (const sign of ZODIAC_SIGNS) {
        output.readings[sign] = {};
        console.log(`♈ ${sign.toUpperCase()}`);

        for (const period of PERIODS) {
            process.stdout.write(`  ${period}... `);

            const prompt = buildPrompt(sign, period);
            const reading = await callGroq(prompt);

            if (reading) {
                output.readings[sign][period] = reading;
                success++;
                console.log('✅');
            } else {
                failed++;
                console.log('❌ (will use fallback)');
            }

            // Respect rate limits: ~2 seconds between calls
            await sleep(2200);
        }
        console.log('');
    }

    // Write output
    const outputDir = path.join(__dirname, '..', 'public', 'data');
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    const outputFile = path.join(outputDir, 'horoscopes.json');
    fs.writeFileSync(outputFile, JSON.stringify(output, null, 2), 'utf-8');

    console.log('══════════════════════════════');
    console.log(`✅ Success: ${success} / ${success + failed}`);
    console.log(`❌ Failed: ${failed}`);
    console.log(`📁 Output: ${outputFile}`);
    console.log(`📦 Size: ${(fs.statSync(outputFile).size / 1024).toFixed(1)} KB`);
}

main().catch(console.error);
