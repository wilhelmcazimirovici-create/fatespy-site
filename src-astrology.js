// src-astrology.js
const ephemeris = require('ephemeris');

const ASTRO_PLANETS = {
    "sun": "Sun",
    "moon": "Moon",
    "mercury": "Mercury",
    "venus": "Venus",
    "mars": "Mars",
    "jupiter": "Jupiter",
    "saturn": "Saturn",
    "uranus": "Uranus",
    "neptune": "Neptune",
    "pluto": "Pluto"
};

const ZODIAC_SIGNS = ["Aries", "Taurus", "Gemini", "Cancer", "Leo", "Virgo", "Libra", "Scorpio", "Sagittarius", "Capricorn", "Aquarius", "Pisces"];

window.getEphemerisData = function (dateObj) {
    if (!ephemeris || !ephemeris.getAllPlanets) return null;

    // We use all zeroes for longitude, latitude, altitude and calculate the geocentric positions
    const ephem = ephemeris.getAllPlanets(dateObj, 0, 0, 0);
    if (!ephem || !ephem.observed) return null;

    let results = [];

    for (const [key, name] of Object.entries(ASTRO_PLANETS)) {
        if (ephem.observed[key]) {
            const planetData = ephem.observed[key];
            const lon = planetData.apparentLongitudeDd;

            let normalizedLon = lon % 360;
            if (normalizedLon < 0) normalizedLon += 360;

            const signIndex = Math.floor(normalizedLon / 30);
            const sign = ZODIAC_SIGNS[signIndex];
            const degree = (normalizedLon % 30).toFixed(2);

            results.push({
                planet: name,
                sign: sign,
                degree: parseFloat(degree),
                longitude: normalizedLon
            });
        }
    }

    return results;
};

window.formatEphemerisForPrompt = function (ephemerisData) {
    if (!ephemerisData || ephemerisData.length === 0) return "";
    let str = "Exact Ephemeris Positions (ABSOLUTE FACT - Use this for accuracy!):\n";
    ephemerisData.forEach(p => {
        str += `- ${p.planet}: ${p.degree}° ${p.sign}\n`;
    });
    return str;
};
