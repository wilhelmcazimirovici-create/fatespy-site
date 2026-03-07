const MAJOR_ARCANA = [
    { number: "0",    name: "The Fool",         symbol: "0",    theme: "New beginnings, innocence, spontaneity",             image: "/assets/images/tarot/the-fool.png" },
    { number: "I",    name: "The Magician",      symbol: "I",    theme: "Manifestation, resourcefulness, power",               image: "/assets/images/tarot/the-magician.png" },
    { number: "II",   name: "The High Priestess",symbol: "II",   theme: "Intuition, sacred knowledge, divine feminine",        image: "/assets/images/tarot/the-high-priestess.png" },
    { number: "III",  name: "The Empress",       symbol: "III",  theme: "Femininity, beauty, nature, abundance",               image: "/assets/images/tarot/the-empress.png" },
    { number: "IV",   name: "The Emperor",       symbol: "IV",   theme: "Authority, establishment, structure",                 image: "/assets/images/tarot/the-emperor.png" },
    { number: "V",    name: "The Hierophant",    symbol: "V",    theme: "Spiritual wisdom, religious beliefs, conformity",     image: "/assets/images/tarot/the-hierophant.png" },
    { number: "VI",   name: "The Lovers",        symbol: "VI",   theme: "Love, harmony, relationships, values alignment",     image: null },
    { number: "VII",  name: "The Chariot",       symbol: "VII",  theme: "Control, willpower, success, action",                image: null },
    { number: "VIII", name: "Strength",          symbol: "VIII", theme: "Strength, courage, persuasion, influence",           image: null },
    { number: "IX",   name: "The Hermit",        symbol: "IX",   theme: "Soul-searching, introspection, being alone",         image: null },
    { number: "X",    name: "Wheel of Fortune",  symbol: "X",    theme: "Good luck, karma, life cycles, destiny",             image: null },
    { number: "XI",   name: "Justice",           symbol: "XI",   theme: "Justice, fairness, truth, cause and effect",         image: null },
    { number: "XII",  name: "The Hanged Man",    symbol: "XII",  theme: "Pause, surrender, letting go, new perspectives",    image: null },
    { number: "XIII", name: "Death",             symbol: "XIII", theme: "Endings, change, transformation, transition",        image: null },
    { number: "XIV",  name: "Temperance",        symbol: "XIV",  theme: "Balance, moderation, patience, purpose",             image: null },
    { number: "XV",   name: "The Devil",         symbol: "XV",   theme: "Shadow self, attachment, addiction, restriction",    image: null },
    { number: "XVI",  name: "The Tower",         symbol: "XVI",  theme: "Sudden change, upheaval, chaos, revelation",        image: null },
    { number: "XVII", name: "The Star",          symbol: "XVII", theme: "Hope, faith, purpose, renewal, spirituality",       image: null },
    { number: "XVIII",name: "The Moon",          symbol: "XVIII",theme: "Illusion, fear, anxiety, subconscious, intuition",  image: null },
    { number: "XIX",  name: "The Sun",           symbol: "XIX",  theme: "Positivity, fun, warmth, success, vitality",        image: null },
    { number: "XX",   name: "Judgement",         symbol: "XX",   theme: "Judgement, rebirth, inner calling, absolution",     image: null },
    { number: "XXI",  name: "The World",         symbol: "XXI",  theme: "Completion, integration, accomplishment, travel",   image: null }
];

// Functie pentru a calcula cartea universala a zilei bazata pe pozitia Soarelui si a Lunii in astrologie
function getGlobalDailyCard() {
    if (typeof window.getEphemerisData !== 'function') {
        const d = new Date();
        const hash = d.getFullYear() + d.getMonth() + d.getDate();
        return MAJOR_ARCANA[hash % 22];
    }
    
    // Extragem datele reale astrologice pentru astazi
    const today = new Date();
    const ephem = window.getEphemerisData(today);
    let sunLon = 0, moonLon = 0;
    
    if (ephem) {
        const sun = ephem.find(p => p.planet === 'Sun');
        const moon = ephem.find(p => p.planet === 'Moon');
        if (sun) sunLon = sun.longitude;
        if (moon) moonLon = moon.longitude;
    }
    
    // Formula alchemica pentru a alege o carte bazata pe gradele absolute (0-360) planetare
    // Adaugam si un offset bazat pe zi, pentru ca planetele mari se misca incet
    const sum = Math.floor(sunLon + moonLon + today.getDate() * 13);
    const cardIndex = sum % 22;
    
    return MAJOR_ARCANA[cardIndex];
}

// Functie suplimentara pentru a calcula cartea personalizata bazata pe ziua de nastere a utilizatorului si stelele curente
function getPersonalCard(birthDay, birthMonth, birthYear) {
    if (typeof window.getEphemerisData !== 'function') {
        const hash = parseInt(birthDay) + parseInt(birthMonth) * 10 + parseInt(birthYear);
        return MAJOR_ARCANA[hash % 22];
    }

    const today = new Date();
    const ephem = window.getEphemerisData(today);
    let sunLon = 0;
    if (ephem) {
        const sun = ephem.find(p => p.planet === 'Sun');
        if (sun) sunLon = sun.longitude;
    }

    const birthSum = parseInt(birthDay) + parseInt(birthMonth) * parseInt(birthYear);
    // Combina numerele nasterii cu tranzitul Soarelui din prezent
    const finalHash = Math.abs(birthSum + Math.floor(sunLon * 100));
    return MAJOR_ARCANA[finalHash % 22]; 
}

// Export explicit in scope-ul global pentru a le folosi cross-file
window.MAJOR_ARCANA = MAJOR_ARCANA;
window.getGlobalDailyCard = getGlobalDailyCard;
window.getPersonalCard = getPersonalCard;
