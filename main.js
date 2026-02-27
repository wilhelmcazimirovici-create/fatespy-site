/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   FateSpy.com â€” Main JavaScript
   Starfield, Interactions, Animations
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

// â”€â”€â”€â”€â”€ Global State â”€â”€â”€â”€â”€
const appState = {
    isLoggedIn: false,
    hasPalm: false,
    hasFace: false,
    hasCoffee: false,
    weatherContext: { condition: 'unknown', temp: 0 }
};

// â”€â”€â”€â”€â”€ Omni-Synergy Engine â”€â”€â”€â”€â”€
function generateOmniReading(baseContext, defaultMsg) {
    if (!appState.isLoggedIn || (!appState.hasPalm && !appState.hasFace && !appState.hasCoffee)) return defaultMsg;

    let msg = `<br/><br/><strong>[Omni-Synergy Synthesized]</strong><br/>`;
    msg += `<i>Weather Resonance (${appState.weatherContext.condition}):</i> `;

    let parts = [];
    if (appState.hasPalm) {
        parts.push("your life line is slightly interrupted but your heart line is strongâ€”meaning your chances in love are at an all-time high despite recent life changes");
    }
    if (appState.hasFace) {
        parts.push("a dominant blue frequency detected in your facial aura indicates a state of high intuitive receptivity and emotional openness");
    }
    if (appState.hasCoffee) {
        parts.push("the distinct heart symbol resting at the base of your morning coffee cup promises stability and joy in unexpected encounters");
    }

    msg += parts.join(" <strong>+</strong> ") + ".<br/><br/>";
    msg += `<strong>Result for ${baseContext}:</strong> Combining these immediate physical markers, ${defaultMsg.toLowerCase()} However, trust your deep intuition today, as your unique biomarkers show an extraordinary alignment with the current environment.`;

    return msg;
}

// â”€â”€â”€â”€â”€ Starfield Canvas â”€â”€â”€â”€â”€
class Starfield {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.stars = [];
        this.shootingStars = [];
        this.resize();
        this.init();
        window.addEventListener('resize', () => this.resize());
        this.animate();
    }

    resize() {
        this.w = this.canvas.width = this.canvas.parentElement.offsetWidth;
        this.h = this.canvas.height = this.canvas.parentElement.offsetHeight;
    }

    init() {
        const count = Math.floor((this.w * this.h) / 3000);
        this.stars = Array.from({ length: count }, () => ({
            x: Math.random() * this.w,
            y: Math.random() * this.h,
            r: Math.random() * 1.8 + 0.3,
            alpha: Math.random(),
            speed: Math.random() * 0.005 + 0.002,
            phase: Math.random() * Math.PI * 2,
        }));
    }

    spawnShootingStar() {
        if (Math.random() > 0.995 && this.shootingStars.length < 2) {
            this.shootingStars.push({
                x: Math.random() * this.w * 0.7,
                y: Math.random() * this.h * 0.4,
                len: Math.random() * 60 + 40,
                speed: Math.random() * 6 + 4,
                alpha: 1,
            });
        }
    }

    animate() {
        const { ctx, w, h } = this;
        ctx.clearRect(0, 0, w, h);

        // Stars
        const now = Date.now() * 0.001;
        for (const s of this.stars) {
            const twinkle = 0.4 + Math.sin(now * s.speed * 200 + s.phase) * 0.6;
            ctx.beginPath();
            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(220, 215, 255, ${twinkle * 0.8})`;
            ctx.fill();
        }

        // Shooting stars
        this.spawnShootingStar();
        for (let i = this.shootingStars.length - 1; i >= 0; i--) {
            const ss = this.shootingStars[i];
            ctx.beginPath();
            ctx.moveTo(ss.x, ss.y);
            ctx.lineTo(ss.x - ss.len, ss.y - ss.len * 0.3);
            ctx.strokeStyle = `rgba(212, 168, 83, ${ss.alpha})`;
            ctx.lineWidth = 1.5;
            ctx.stroke();

            // Small glow at head
            ctx.beginPath();
            ctx.arc(ss.x, ss.y, 2, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(240, 212, 138, ${ss.alpha})`;
            ctx.fill();

            ss.x += ss.speed;
            ss.y += ss.speed * 0.3;
            ss.alpha -= 0.015;
            if (ss.alpha <= 0) this.shootingStars.splice(i, 1);
        }

        requestAnimationFrame(() => this.animate());
    }
}

// â”€â”€â”€â”€â”€ Header Scroll Effect â”€â”€â”€â”€â”€
function setupHeaderScroll() {
    const header = document.getElementById('site-header');
    let ticking = false;

    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                header.classList.toggle('scrolled', window.scrollY > 50);
                ticking = false;
            });
            ticking = true;
        }
    });
}

// â”€â”€â”€â”€â”€ Mobile Menu â”€â”€â”€â”€â”€
function setupMobileMenu() {
    const hamburger = document.getElementById('hamburger');
    const nav = document.getElementById('main-nav');

    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        nav.classList.toggle('open');
        document.body.style.overflow = nav.classList.contains('open') ? 'hidden' : '';
    });

    // Close on link click
    nav.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            nav.classList.remove('open');
            document.body.style.overflow = '';
        });
    });
}

// â”€â”€â”€â”€â”€ Tarot Card Flip & Omni Synergy â”€â”€â”€â”€â”€
function setupTarotFlip() {
    const widget = document.getElementById('widget-tarot');
    widget.addEventListener('click', () => {
        widget.classList.toggle('flipped');

        // Show modal with omni-synergy reading for Tarot
        setTimeout(() => {
            const modal = document.getElementById('global-modal');
            const modalTitle = document.getElementById('modal-title');
            const modalBody = document.getElementById('modal-body');
            if (modal && appState.isLoggedIn) {
                const defaultMsg = "The Sun brings joy, success, and positive clarity to your endeavors.";
                const synergyMsg = generateOmniReading('The Sun (Tarot)', defaultMsg);

                modalTitle.textContent = "Tarot Omni-Synergy";
                modalBody.innerHTML = synergyMsg;
                modal.classList.add('active');
            }
        }, 800);
    });
}

// â”€â”€â”€â”€â”€ Scroll-to-Top Button â”€â”€â”€â”€â”€
function setupScrollTop() {
    const btn = document.getElementById('scroll-top');

    window.addEventListener('scroll', () => {
        btn.classList.toggle('visible', window.scrollY > 600);
    });

    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// â”€â”€â”€â”€â”€ Counter Animation (Social Proof) â”€â”€â”€â”€â”€
function setupCounters() {
    const counters = document.querySelectorAll('.stat-number');
    const duration = 2000;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.dataset.target, 10);
                const start = performance.now();

                function step(now) {
                    const elapsed = now - start;
                    const progress = Math.min(elapsed / duration, 1);
                    // Ease-out quad
                    const ease = 1 - (1 - progress) * (1 - progress);
                    el.textContent = Math.floor(ease * target).toLocaleString('ro-RO');
                    if (progress < 1) requestAnimationFrame(step);
                    else el.textContent = target.toLocaleString('ro-RO') + '+';
                }

                requestAnimationFrame(step);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(c => observer.observe(c));
}

// â”€â”€â”€â”€â”€ Card Entrance Animations â”€â”€â”€â”€â”€
function setupCardAnimations() {
    const cards = document.querySelectorAll('.bento-card, .widget-card');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                entry.target.style.transitionDelay = `${i * 80}ms`;
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
        observer.observe(card);
    });
}

// Add visible state styles
const style = document.createElement('style');
style.textContent = `
  .bento-card.visible, .widget-card.visible {
    opacity: 1 !important;
    transform: translateY(0) !important;
  }
`;
document.head.appendChild(style);

// â”€â”€â”€â”€â”€ Moon Phase Calculator â”€â”€â”€â”€â”€
function calculateMoonPhase() {
    const now = new Date();
    // Simple lunar cycle calculation (29.53 days)
    const year = now.getFullYear();
    const month = now.getMonth() + 1;
    const day = now.getDate();

    let c = 0, e = 0, jd = 0, b = 0;
    if (month < 3) {
        c = 365.25 * (year - 1);
        e = 30.6001 * (month + 13);
    } else {
        c = 365.25 * year;
        e = 30.6001 * (month + 1);
    }
    jd = c + e + day - 694039.09;
    jd /= 29.5305882;
    b = parseInt(jd);
    jd -= b;
    const phase = Math.round(jd * 8);

    const phases = [
        { name: 'Lun\u0103 Nou\u0103', icon: '\uD83C\uDF11' },
        { name: 'Lun\u0103 Cresc\u0103toare', icon: '\uD83C\uDF12' },
        { name: 'Prim P\u0103trar', icon: '\uD83C\uDF13' },
        { name: 'Gibboas\u0103 Cresc\u0103toare', icon: '\uD83C\uDF14' },
        { name: 'Lun\u0103 Plin\u0103', icon: '\uD83C\uDF15' },
        { name: 'Gibboas\u0103 Descresc\u0103toare', icon: '\uD83C\uDF16' },
        { name: 'Ultimul P\u0103trar', icon: '\uD83C\uDF17' },
        { name: 'Lun\u0103 Descresc\u0103toare', icon: '\uD83C\uDF18' },
    ];

    const moonTexts = [
        'Moment ideal pentru noi \u00EEnceputuri \u0219i inten\u021Bii.',
        'Energia cre\u0219te. Timp de ac\u021Biune \u0219i construire.',
        'Perioad\u0103 de ac\u021Biune. Energia e \u00EEn cre\u0219tere.',
        'Aproape de \u00EEmplinire. R\u0103bdare \u0219i perseveren\u021B\u0103.',
        'Energii la maxim. Timp de recoltare \u0219i recuno\u0219tin\u021B\u0103.',
        'Reflectare \u0219i recuno\u0219tin\u021B\u0103. Las\u0103 lucrurile s\u0103 vin\u0103.',
        'Timp de eliberare. Renun\u021B\u0103 la ce nu \u00EE\u021Bi mai serve\u0219te.',
        'Odihn\u0103 \u0219i introspec\u021Bie. Preg\u0103tire pentru un nou ciclu.',
    ];
    const idx = phase % 8;
    const globalZodiac = document.getElementById('horoscope-zodiac');

    // --- Weather Synergy Logic ---
    const dynWeatherMsg = document.getElementById('dyn-weather-msg');
    const weatherLoc = document.getElementById('weather-location');
    const weatherDesc = document.getElementById('weather-desc');
    const weatherIcon = document.getElementById('weather-icon');

    const updateWeatherContext = (temp, condition, city) => {
        appState.weatherContext.condition = condition;
        appState.weatherContext.temp = temp;

        if (weatherLoc) weatherLoc.textContent = city + ' (' + temp + '\u00B0C)';
        if (weatherDesc) weatherDesc.textContent = "Current atmosphere: " + condition;

        let synergy = "";
        if (condition.toLowerCase().includes('clear') || condition.toLowerCase().includes('sun')) {
            if (weatherIcon) weatherIcon.textContent = "☀️";
            synergy = "Today, because the weather is clear and beautiful, you have high chances to go out and meet your soulmate or find new opportunities.";
        } else if (condition.toLowerCase().includes('rain') || condition.toLowerCase().includes('drizzle')) {
            if (weatherIcon) weatherIcon.textContent = "🌧️";
            synergy = "The rain outside reflects a time for deep introspection. Stay cozy and focus on healing your inner emotional landscape.";
        } else if (condition.toLowerCase().includes('cloud')) {
            if (weatherIcon) weatherIcon.textContent = "☁️";
            synergy = "Cloudy skies bring a mysterious aura today. It's a perfect time to meditate and uncover hidden truths about your career.";
        } else {
            if (weatherIcon) weatherIcon.textContent = "✨";
            synergy = "The cosmic energies are aligning with your local environment to bring unexpected shifts today.";
        }

        if (dynWeatherMsg) dynWeatherMsg.textContent = synergy;
    };

    const fetchWeather = async (lat, lon) => {
        try {
            // Using Open-Meteo as a free no-auth API
            const res = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true`);
            const data = await res.json();
            const temp = data.current_weather.temperature;
            const code = data.current_weather.weathercode;

            // Reverse geocode to get a rough city name (using a free service)
            const geoRes = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`);
            const geoData = await geoRes.json();
            const city = geoData.address.city || geoData.address.town || geoData.address.village || 'Your Location';

            let condition = "Unknown";
            if (code <= 3) condition = "Clear/Partly Cloudy";
            else if (code >= 51 && code <= 67) condition = "Rainy";
            else if (code >= 71 && code <= 77) condition = "Snowy";
            else condition = "Cloudy";

            updateWeatherContext(temp, condition, city);

        } catch (e) {
            console.error("Weather fetch failed", e);
            updateWeatherContext("--", "Unknown", "Unknown Location");
        }
    };

    // Try GeoLocation
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(
            (position) => fetchWeather(position.coords.latitude, position.coords.longitude),
            (error) => updateWeatherContext(22, "Clear", "Default Layout (Geo Denied)")
        );
    } else {
        updateWeatherContext(22, "Clear", "Default");
    }

    globalZodiac.addEventListener('change', (e) => {
        const val = e.target.value;
        if (!val) return; // placeholder selected
        const selectedKey = 'zodiac_' + val;

        // update global icon
        const zodiacIcons = {
            aries: 'â™ˆ', taurus: 'â™‰', gemini: 'â™Š', cancer: 'â™‹', leo: 'â™Œ', virgo: 'â™', libra: 'â™Ž', scorpio: 'â™', sagittarius: 'â™', capricorn: 'â™‘', aquarius: 'â™’', pisces: 'â™“'
        };
        const iconEl = document.getElementById('global-zodiac-icon');
        if (iconEl && zodiacIcons[val]) iconEl.textContent = zodiacIcons[val];

        // Reveal horoscope content (hidden until first selection)
        const promptL = document.getElementById('horoscope-prompt-left');
        const promptR = document.getElementById('horoscope-prompt-right');
        const traitsContent = document.getElementById('horoscope-traits-content');
        const readingContent = document.getElementById('horoscope-reading-content');
        if (promptL) promptL.style.display = 'none';
        if (promptR) promptR.style.display = 'none';
        if (traitsContent) traitsContent.style.display = 'flex';
        if (readingContent) readingContent.style.display = 'flex';

        // update trait based on zodiac (instant fallback + async AI)
        const tl = document.getElementById('trait-love');
        const tc = document.getElementById('trait-career');
        const tm = document.getElementById('trait-money');
        const th = document.getElementById('trait-health');
        const thm = document.getElementById('trait-home');

        const traits = {
            aries: {
                love: 'Aries approach love with fiery passion and straightforward honesty. Expect spontaneous gestures, but beware of their quick temper, which passes as fast as it arrives.',
                career: 'Natural leaders, Aries thrive when taking charge of new projects. Their bold initiative often catches the eyes of managers, though they struggle with patience.',
                money: 'Often impulsive with spending, an Aries needs to consciously pause before large purchases. However, their competitive drive makes them great negotiators.',
                health: 'Characterized by high physical vitality, they need rigorous exercise to burn off excess energy and keep stress-induced headaches at bay.',
                home: 'Aries like to be the boss of the house. They tackle chores with a burst of speed but might leave projects half-finished if they get bored.'
            },
            taurus: {
                love: 'Taurus values loyalty, sensual affection, and steadfast commitment. Once they choose a partner, they are devoted and protective, though occasionally stubborn.',
                career: 'Reliable and immensely hardworking, Taurus prefers steady structural growth over risky leaps. Colleagues often lean on their practical problem-solving.',
                money: 'Excellent with finances, Taurus rarely makes impulsive moves. They have a talent for building long-term wealth and prefer investing in tangible, luxurious assets.',
                health: 'Taurus generally enjoys robust health but can struggle with overindulgence. Routine is key, as is taking time for peaceful, grounding activities.',
                home: 'A Taurus home is a sanctuary of comfort and luxury. They take deep pride in creating a secure, beautifully decorated environment for their family.'
            },
            gemini: {
                love: 'Communication and mental stimulation are everything for Gemini. They are flirtatious, fun, and seek partners who can keep up with their rapid-fire wit.',
                career: 'Highly adaptable, Gemini excels in roles involving writing, speaking, or rapid task-switching. They are excellent networkers but can easily become bored.',
                money: 'Gemini views money as a means to fund their curiosities. They might struggle with budgeting due to scattering their resources across too many interests.',
                health: 'Mental burnout is a frequent issue for Gemini. They need to consciously disconnect from screens and engage their bodies to calm their racing minds.',
                home: 'Their living space is often filled with books, gadgets, and ongoing projects. They prefer a lively household where guests frequently come and go.'
            },
            cancer: {
                love: 'Cancers seek deep, emotional security and fiercely protect their loved ones. They are highly empathetic and intuitive, but prone to defensive mood swings.',
                career: 'They thrive in nurturing or supportive roles. While they may not seek the spotlight, their loyalty and emotional intelligence make them invaluable team players.',
                money: 'Cautious and frugal, Cancer saves for a rainy day. They view money as a tool for security and are unlikely to take significant financial risks.',
                health: 'Emotional stress profoundly impacts their physical well-being. Regular relaxation and a safe retreat are vital to processing their deep feelings.',
                home: 'Home is a sacred shell for Cancer. They excel at domestic matters, creating a deeply cozy, emotionally supportive environment centered around family.'
            },
            leo: {
                love: 'Leos love grand, cinematic romance. They are incredibly generous and loyal partners who expect affection, respect, and frequent validation in return.',
                career: 'Natural performers and leaders, Leos naturally draw attention. They excel in managerial roles where their charisma and creative vision can shine.',
                money: 'Leos enjoy spending money on high-quality items and loved ones. While generous, they need to ensure their taste for luxury doesn\'t eclipse their income.',
                health: 'Heart and spine health are crucial areas. While generally robust, Leos benefit from cardiovascular exercise that allows them to show off their vitality.',
                home: 'Their home is their castle, often decorated warmly and boldly. They love hosting gatherings where they can entertain their close-knit circle.'
            },
            virgo: {
                love: 'Virgos show love through acts of service and meticulous attention to detail. They can be perfectionists in relationships but are deeply committed and supportive.',
                career: 'Highly analytical and organized, Virgos are the engine of any workplace. They excel at refining processes, though they must guard against micromanagement.',
                money: 'Practical and detail-oriented, Virgos are excellent budgeters. They rarely make impulsive buys and heavily research any potential investments.',
                health: 'Often plagued by nervous tension and digestive issues. Virgos must prioritize gut health and incorporate structured relaxation into their routines.',
                home: 'A Virgo home is highly organized, clean, and functional. They deal with chores systematically and appreciate household members who respect their systems.'
            },
            libra: {
                love: 'Libras are the ultimate romantics, constantly seeking harmony, beauty, and balance. They excel at compromise but may avoid necessary conflict.',
                career: 'Diplomatic and cooperative, Libras are excellent mediators and team leaders. They thrive in aesthetically pleasing environments or roles involving justice.',
                money: 'Libras appreciate the finer things in life, particularly art and fashion. They must balance their desire for luxury with practical financial planning.',
                health: 'Balance in all things is Libras health motto. They benefit from gentle, harmonizing exercises like yoga, and avoiding extreme physical stress.',
                home: 'Their home must be aesthetically beautiful and socially inviting. They despise domestic conflict and will go out of their way to mediate family disputes.'
            },
            scorpio: {
                love: 'Intense, passionate, and fiercely loyal, Scorpios demand complete emotional transparency. They bond deeply but struggle with trust and possessiveness.',
                career: 'Resourceful and focused, Scorpios excel at research and strategy. They operate exceptionally well in a crisis and command deep respect from colleagues.',
                money: 'Scorpios are highly secretive and strategic with finances. They excel at managing shared resources, inheritances, and uncovering hidden financial opportunities.',
                health: 'Scorpios possess immense regenerative energy but can hold onto toxic emotions. Psychological detoxing and intense physical workouts are highly beneficial.',
                home: 'They treat their home as a private, secure fortress. Domestic matters are handled decisively, and they fiercely protect their familys privacy.'
            },
            sagittarius: {
                love: 'Freedom-loving and brutally honest, Sagittarius needs a relationship that feels like an adventure. Clinginess will cause them to quickly run away.',
                career: 'They thrive in dynamic, expansive roles involving travel, teaching, or philosophy. Routine office politics suffocate their need for constant growth.',
                money: 'Optimistic about resources, they might spend impulsively on experiences over objects. They trust that money will easily flow back to them.',
                health: 'Generally robust and active, but prone to overextending themselves in their pursuit of fun. Outdoor activities are vital for their well-being.',
                home: 'Their home is often seen as a temporary basecamp between adventures. Its filled with souvenirs, but they dislike feeling tied down by heavy domestic duties.'
            },
            capricorn: {
                love: 'Capricorns are traditional, disciplined, and cautious in love. They seek a partner who shares their ambitions and values long-term stability and respect.',
                career: 'The ultimate professionals, Capricorns are ambitious, structured, and capable of enduring immense pressure to achieve top-tier executive success.',
                money: 'Highly conservative and strategic with money. Capricorns are excellent at long-term wealth building, preferring secure, status-enhancing investments.',
                health: 'Prone to issues with bones and joints, particularly the knees. Managing workaholic tendencies is their biggest challenge to maintaining holistic health.',
                home: 'They run their household with firm authority and structure. Capricorns prioritize providing a highly stable, materially secure foundation for their family.'
            },
            aquarius: {
                love: 'Independent and intellectually driven, Aquarius values deep friendship above all in romance. They need a partner who completely respects their personal freedom.',
                career: 'Innovative and forward-thinking, they excel in tech, science, or humanitarian roles. They prefer working in collective settings rather than traditional hierarchies.',
                money: 'Their approach to money is often eccentric. They might donate generously to causes they believe in while living surprisingly frugally in other areas.',
                health: 'Circulation and nervous system issues are common. They need to ensure they dont get too stuck in their heads, remembering to engage physically.',
                home: 'An Aquarius home is quirky, filled with the latest gadgets, and often serves as a hub for their large, diverse network of friends and associates.'
            },
            pisces: {
                love: 'Deeply romantic and spiritually inclined, Pisces seeks a soulful merger. They are incredibly compassionate but can easily fall into the role of a martyr.',
                career: 'Highly imaginative and empathetic, they excel in artistic, healing, or intuitive professions. They often struggle with rigid corporate structures.',
                money: 'Money slips through their fingers easily as they are driven by feeling rather than logic. They benefit from having a structured financial advisor.',
                health: 'Pisces are spiritual sponges, absorbing the energy around them. They require immense amounts of sleep and solitary retreat to recharge their immune system.',
                home: 'Their home is an emotional retreat, often whimsical and slightly disorganized. It serves as a safe harbor where they can dream and drift peacefully.'
            }
        };

        // Show hardcoded traits immediately (instant response)
        if (tl && tc && tm && th && thm) {
            const zData = traits[val] || traits['aries'];
            tl.textContent = zData.love;
            tc.textContent = zData.career;
            tm.textContent = zData.money;
            th.textContent = zData.health;
            thm.textContent = zData.home;
        }

        if (window.updateI18nElement) {
            const dict = window.getTranslations();
            let defaultTxt = "Horoscope for " + (dict[selectedKey] || val) + " updated.";
            // Append Omni-Synergy if active
            if (appState.isLoggedIn) {
                defaultTxt = generateOmniReading('Daily Horoscope', defaultTxt);
            }
            const widgetTextEl = document.getElementById('text-horoscope-daily');
            if (widgetTextEl) widgetTextEl.innerHTML = defaultTxt;
        }

        // re-trigger period update to refresh right column horoscope text
        const periodEl = document.getElementById('horoscope-period');
        if (periodEl) periodEl.dispatchEvent(new Event('change'));
    });

    // â•â•â•â•â•â• AI-Generated Horoscope Data (pre-generated daily) â•â•â•â•â•â•
    let aiHoroscopes = null;
    fetch('/data/horoscopes.json')
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (data && data.readings) {
                aiHoroscopes = data.readings;
                console.log('ðŸ”® FateSpy AI horoscopes loaded:', data.generated_at);
            }
        })
        .catch(() => console.warn('AI horoscopes not available, using fallback'));

    // Compact fallback readings
    const fallbackReadings = {
        daily: {
            aries: { love: 'Bold romantic energy surrounds you today. Take the lead in expressing your feelings.', career: 'Your initiative catches attention at work. Push forward with that new idea.', money: 'Be mindful of impulse purchases today. Review your budget before spending.', health: 'Channel your high energy into vigorous exercise. Stay hydrated throughout the day.', home: 'Lead by example at home today. Tackle that chore you\'ve been putting off.' },
            taurus: { love: 'Sensual, comforting energy fills your love life today. Plan a cozy evening.', career: 'Steady progress on projects is favored. Your reliability is noticed by management.', money: 'Financial stability is strong. Consider a long-term savings plan today.', health: 'Ground yourself with nature and whole foods. Your body craves stability.', home: 'Your home is your sanctuary today. Add small comforts that bring you joy.' },
            gemini: { love: 'Flirty conversations and playful banter dominate your romantic sphere today.', career: 'Communication tasks flow easily. Schedule important meetings and pitches.', money: 'Multiple small income opportunities appear. Keep track of all streams.', health: 'Rest your eyes from screens periodically. Balance mental activity with movement.', home: 'Lively household conversations energize everyone. Share stories over dinner.' },
            cancer: { love: 'Deep emotional connections are highlighted today. Nurture your closest bonds.', career: 'Your intuition about that project is spot-on. Trust your gut feelings.', money: 'Save rather than spend today. Your cautious financial instincts serve you well.', health: 'Emotional health needs attention. Journal or talk to someone you trust.', home: 'Family bonds strengthen through small nurturing gestures today.' },
            leo: { love: 'Grand romantic gestures are favored today. Express your feelings boldly.', career: 'Your charisma peaks today. Rally your team around your creative vision.', money: 'Set a budget for generous impulses. Your heart is bigger than your wallet today.', health: 'Cardiovascular exercise channels your abundant energy positively.', home: 'Hosting and entertaining at home is highly favored. Show off your warmth.' },
            virgo: { love: 'Show love through thoughtful acts of service today. Actions speak volumes.', career: 'Detail-oriented work goes exceptionally well. Refine and optimize your processes.', money: 'Review subscriptions and recurring charges. Small savings add up significantly.', health: 'Digestive health needs care today. Eat mindfully and consider probiotics.', home: 'Tackle that organizational project at home. You\'ll feel deeply satisfied.' },
            libra: { love: 'Harmony in partnerships is the theme today. Seek balance and beauty.', career: 'Collaborative projects shine. Your diplomatic skills resolve team challenges.', money: 'Joint financial decisions require balanced input from all parties.', health: 'Yoga or stretching helps align your physical and mental equilibrium today.', home: 'Resolve any household disagreements diplomatically for lasting peace.' },
            scorpio: { love: 'Intense emotional undercurrents run through your love life. Honesty is key.', career: 'Strategic thinking gives you an edge. Dig deeper before deciding.', money: 'Hidden financial opportunities may surface. Look into overlooked benefits.', health: 'Release stored tension through intense physical activity today.', home: 'Privacy matters today. Create a personal space where you can recharge.' },
            sagittarius: { love: 'Adventure calls in romance. Surprise your partner with spontaneity.', career: 'Think big today. A visionary idea could gain unexpected traction.', money: 'Spend on experiences wisely. Joy doesn\'t require extravagance.', health: 'Outdoor activities are your best medicine. Fresh air lifts body and spirit.', home: 'Rearranging furniture or planning a trip channels your restless energy.' },
            capricorn: { love: 'A meaningful conversation about the future strengthens your relationship.', career: 'Leadership responsibilities increase. Take charge with calm authority.', money: 'Investment planning is favored. Research conservative, reliable options.', health: 'Pay attention to posture. Stand up and stretch hourly if desk-bound.', home: 'Household systems you set up today will reduce future stress significantly.' },
            aquarius: { love: 'An unconventional romantic opportunity presents itself. Keep an open mind.', career: 'Innovation is your superpower today. Propose that unconventional solution.', money: 'An unexpected expense may arise, but your resourcefulness handles it.', health: 'Deep breathing exercises calm your nervous system effectively today.', home: 'A tech upgrade catches your eye. Research thoroughly before purchasing.' },
            pisces: { love: 'Your empathy draws people closer today. Trust your heart\'s intuition.', career: 'Let imagination guide your work today. Creative output is exceptional.', money: 'Protect your reserves. Avoid lending unless prepared to gift it.', health: 'Sleep quality matters most today. Create a restful environment tonight.', home: 'Soft music and gentle lighting create the peaceful atmosphere you need.' }
        }
    };
    ['weekly', 'monthly', 'yearly'].forEach(p => { fallbackReadings[p] = fallbackReadings.daily; });

    // Setup Period Selector
    const periodSelector = document.getElementById('horoscope-period');
    if (periodSelector) {
        periodSelector.addEventListener('change', (e) => {
            const pVal = e.target.value;
            const zVal = globalZodiac ? globalZodiac.value : 'aries';
            if (!zVal) return;
            const labelEl = document.getElementById('label-horoscope-period');
            const elLove = document.getElementById('text-horoscope-love');
            const elCareer = document.getElementById('text-horoscope-career');
            const elMoney = document.getElementById('text-horoscope-money');
            const elHealth = document.getElementById('text-horoscope-health');
            const elHome = document.getElementById('text-horoscope-home');

            if (labelEl) {
                labelEl.textContent = pVal.charAt(0).toUpperCase() + pVal.slice(1) + " Horoscope";
            }

            if (elLove && elCareer && elMoney && elHealth && elHome) {
                // Try AI-generated data first, then fallback
                let reading = null;
                if (aiHoroscopes && aiHoroscopes[zVal] && aiHoroscopes[zVal][pVal]) {
                    reading = aiHoroscopes[zVal][pVal];
                } else if (fallbackReadings[pVal] && fallbackReadings[pVal][zVal]) {
                    reading = fallbackReadings[pVal][zVal];
                }

                if (reading) {
                    elLove.textContent = reading.love;
                    elCareer.textContent = reading.career;
                    elMoney.textContent = reading.money;
                    elHealth.textContent = reading.health;
                    elHome.textContent = reading.home;
                }
            }
        });
    }





    // populate selects for Birth chart inline
    // daySel, monthSel etc. were defined before
    const daySel = document.getElementById('birth-day');
    const monthSel = document.getElementById('birth-month');
    const yearSel = document.getElementById('birth-year');
    const hourSel = document.getElementById('birth-hour');
    const minSel = document.getElementById('birth-minute');

    if (daySel) {
        for (let i = 1; i <= 31; i++) daySel.appendChild(new Option(i, i));
        daySel.value = new Date().getDate();
    }
    if (monthSel) {
        for (let i = 1; i <= 12; i++) monthSel.appendChild(new Option(i, i));
        monthSel.value = new Date().getMonth() + 1;
    }
    if (yearSel) {
        let currentYear = new Date().getFullYear();
        for (let i = currentYear; i >= 1920; i--) yearSel.appendChild(new Option(i, i));
        yearSel.value = 1990;
    }
    if (hourSel) {
        for (let i = 0; i <= 23; i++) hourSel.appendChild(new Option(i.toString().padStart(2, '0'), i));
    }
    if (minSel) {
        for (let i = 0; i <= 59; i++) minSel.appendChild(new Option(i.toString().padStart(2, '0'), i));
    }

    // ── Personalized Horoscope Form ──
    const phDay = document.getElementById('ph-day');
    const phYear = document.getElementById('ph-year');
    const phTime = document.getElementById('ph-time');
    const phMonth = document.getElementById('ph-month');

    if (phDay) {
        for (let i = 1; i <= 31; i++) phDay.appendChild(new Option(i, i));
        phDay.value = new Date().getDate();
    }
    if (phYear) {
        const cy = new Date().getFullYear();
        for (let i = cy; i >= 1920; i--) phYear.appendChild(new Option(i, i));
        phYear.value = 1990;
    }
    if (phTime) {
        // Combined time dropdown: every 30 minutes
        for (let h = 0; h < 24; h++) {
            for (let m = 0; m < 60; m += 30) {
                const label = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
                phTime.appendChild(new Option(label, `${h}:${m}`));
            }
        }
        phTime.value = '12:0';
    }
    if (phMonth) phMonth.value = new Date().getMonth() + 1;

    // Submit handler → navigate to personal-horoscope.html
    const btnPersonal = document.getElementById('btn-personal-horoscope');
    if (btnPersonal) {
        btnPersonal.addEventListener('click', () => {
            const day = document.getElementById('ph-day')?.value || '1';
            const month = document.getElementById('ph-month')?.value || '1';
            const year = document.getElementById('ph-year')?.value || '1990';
            const timeVal = document.getElementById('ph-time')?.value || '12:0';
            const [hour, minute] = timeVal.split(':');
            const place = document.getElementById('ph-place')?.value.trim() || '';
            const gender = document.getElementById('ph-gender')?.value || 'male';

            const params = new URLSearchParams({ day, month, year, hour, minute, place, gender });
            window.location.href = `/personal-horoscope.html?${params.toString()}`;
        });
    }

    const moonIcon = document.getElementById('moon-phase-icon');
    const moonName = document.getElementById('moon-phase-name');
    const moonText = moonIcon?.closest('.widget-card')?.querySelector('.widget-text');

    if (moonIcon) moonIcon.textContent = phases[idx].icon;
    if (moonName) moonName.textContent = phases[idx].name;
    if (moonText) moonText.textContent = moonTexts[idx];
}

function setupModalAndServices() {
    const modal = document.getElementById('global-modal');
    const modalClose = document.getElementById('modal-close');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');

    const showModal = (title, contentHTML) => {
        modalTitle.textContent = title;
        modalBody.innerHTML = contentHTML;
        modal.classList.add('active');
    };

    const hideModal = () => {
        modal.classList.remove('active');
    };

    if (!modal) return;

    modalClose.addEventListener('click', hideModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideModal();
    });

    // Setup Compatibility Calculator
    const compatBtn = document.getElementById('compat-btn');
    if (compatBtn) {
        compatBtn.addEventListener('click', () => {
            const z1 = document.getElementById('compat-z1').value;
            const z2 = document.getElementById('compat-z2').value;

            if (!z1 || !z2) {
                showModal('Error', '<p style="color:var(--accent-purple);">Please select both zodiac signs.</p>');
                return;
            }

            // Simulation of AI processing
            const score = Math.floor(Math.random() * 41) + 60; // 60-100%
            let msg = "";
            if (score > 90) msg = "Soulmates! You share a profound connection.";
            else if (score > 80) msg = "Great compatibility. Fire and air fuel each other.";
            else if (score > 70) msg = "Good match, but requires compromise and understanding.";
            else msg = "Challenging but rewarding if you put in the effort.";

            showModal('Compatibility Result', `
        <div style="font-size:3rem; font-weight:bold; color:var(--accent-gold); margin-bottom:15px;">${score}%</div>
        <p>${generateOmniReading('Compatibility', msg)}</p>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:15px;">Calculated by FateSpy AI</p>
      `);
        });
    }

    // Setup Birth Chart
    const birthChartBtn = document.querySelector('#card-birthchart .btn');
    if (birthChartBtn) {
        birthChartBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const day = document.getElementById('birth-day').value;
            const month = document.getElementById('birth-month').value;
            const year = document.getElementById('birth-year').value;
            const hour = document.getElementById('birth-hour').value;
            const minute = document.getElementById('birth-minute').value;

            const params = new URLSearchParams({ day, month, year, hour, minute });
            window.location.href = `/birth-chart.html?${params.toString()}`;
        });
    }

    // Setup AI Coffee Reader
    const coffeeCard = document.getElementById('card-coffee');
    if (coffeeCard) {
        coffeeCard.addEventListener('click', (e) => {
            e.preventDefault();
            let msg = "The AI scanned your coffee grounds... The shape of an eagle emerged, symbolizing freedom and an upcoming journey.";
            showModal('AI Coffee Reader', `<p>${generateOmniReading('Coffee Reading', msg)}</p>`);
        });
    }

    // Setup Palmistry AI
    const palmCard = document.getElementById('card-palmistry');
    if (palmCard) {
        palmCard.addEventListener('click', (e) => {
            e.preventDefault();
            let msg = "Scanning life, heart, and head lines... Your life line is robust, indicating high vitality and resilience in the face of obstacles.";
            showModal('Palmistry AI', `<p>${generateOmniReading('Palm Reading', msg)}</p>`);
        });
    }

    // Secondary Bento Cards Modals
    const setupCardModal = (selector, title, defaultMsg) => {
        const card = document.querySelector(selector);
        if (card) {
            card.addEventListener('click', (e) => {
                e.preventDefault();
                showModal(title, `<p>${generateOmniReading(title, defaultMsg)}</p>`);
            });
        }
    };

    setupCardModal('.card-numerologie', 'Numerology', 'Your life path number resonates with building secure foundations and achieving material success.');
    setupCardModal('.card-aura', 'Planetary Transits', 'Uranus is transitioning into your area of career, signaling rapid, positive shifts if you embrace adaptability.');
    setupCardModal('#card-tarot', 'Live Tarot Readings', 'An expert reader is currently available. The cards sense a period of profound emotional transition ahead.');
    setupCardModal('#card-runes', 'Oracle & Runes', 'The drawn rune is Fehu, signaling forthcoming wealth and successful conclusions to your material efforts.');
    setupCardModal('a[href="#clairvoyance"]', 'Clairvoyance Sessions', 'Our clairvoyants are ready to tap into the aether. A prominent white aura suggests spiritual guidance is close.');
    setupCardModal('a[href="#dreams"]', 'Dream Dictionary', 'Dreaming of flying indicates a release of past burdens and soaring towards newborn ambitions.');
    setupCardModal('a[href="#meditations"]', 'Energy Clearing', 'Your root chakra currently requires grounding. Let our sessions help you reconnect with the earth.');
    setupCardModal('a[href="#store"]', 'Esoteric Store', 'Discover crystals, tarot decks, and ritual candles meticulously selected to enhance your spiritual journey.');
    setupCardModal('a[href="#vip"]', 'VIP Subscriptions', 'Unlock exclusive personalized daily reports and direct priority access to our top astrologers.');
    setupCardModal('a[href="#courses"]', 'Courses & Webinars', 'Join our upcoming webinar focusing on decoding your own birth chart practically.');
}

// â”€â”€â”€â”€â”€ Dashboard Auth & Uploads â”€â”€â”€â”€â”€
function setupDashboard() {
    const authBtn = document.getElementById('auth-btn');
    const dashboard = document.getElementById('dashboard');
    const hero = document.getElementById('hero');

    const uiUpdate = () => {
        if (appState.isLoggedIn) {
            if (dashboard) dashboard.style.display = 'block';
            if (hero) hero.style.display = 'none';
            if (authBtn) {
                authBtn.textContent = 'Logout';
                authBtn.href = '#';
                authBtn.style.display = 'inline-flex';
            }
        } else {
            if (dashboard) dashboard.style.display = 'none';
            if (hero) hero.style.display = 'flex';
            if (authBtn) {
                authBtn.textContent = 'Login';
                authBtn.href = '/login.html';
                authBtn.style.display = 'inline-flex';
            }
            // Reset state
            appState.hasPalm = appState.hasFace = appState.hasCoffee = false;
            const omniStatus = document.getElementById('omni-status');
            if (omniStatus) {
                omniStatus.textContent = 'Standby';
                omniStatus.style.color = 'white';
            }
            document.querySelectorAll('#dashboard .bento-card').forEach(c => c.style.borderColor = 'rgba(255,255,255,0.08)');
            document.querySelectorAll('#dashboard .icon').forEach(i => i.style.opacity = '0.5');
        }
    };

    if (authBtn) authBtn.addEventListener('click', (e) => {
        if (appState.isLoggedIn) {
            e.preventDefault();
            appState.isLoggedIn = false;
            localStorage.removeItem('fatespy_token');
            uiUpdate();
        }
        // When not logged in, the href="/login.html" handles navigation
    });

    const checkOmniStatus = () => {
        const count = [appState.hasPalm, appState.hasFace, appState.hasCoffee].filter(Boolean).length;
        const statusEl = document.getElementById('omni-status');
        if (count === 3) {
            statusEl.textContent = 'Active (Max Synergy)';
            statusEl.style.color = 'var(--accent-teal)';
        } else if (count > 0) {
            statusEl.textContent = 'Active (Partial Synergy)';
            statusEl.style.color = 'var(--accent-gold)';
        }
    };

    const setupUpload = (btnId, cardId, iconId, stateKey) => {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', () => {
                appState[stateKey] = true;
                btn.textContent = 'Uploaded âœ“';
                btn.classList.replace('btn-secondary', 'btn-outline');
                document.getElementById(iconId).style.opacity = '1';
                document.getElementById(cardId).style.borderColor = 'var(--accent-purple)';
                checkOmniStatus();

                // Immediately update horoscope base text with new synergy
                const globalZodiac = document.getElementById('horoscope-zodiac');
                if (globalZodiac && window.updateHoroscopeText) {
                    window.updateHoroscopeText(globalZodiac.value);
                }
            });
        }
    };

    setupUpload('btn-upload-palm', 'dash-palm', 'palm-icon', 'hasPalm');
    setupUpload('btn-upload-face', 'dash-face', 'face-icon', 'hasFace');
    setupUpload('btn-upload-coffee', 'dash-coffee', 'coffee-icon', 'hasCoffee');
}

// â”€â”€â”€â”€â”€ Init â”€â”€â”€â”€â”€
document.addEventListener('DOMContentLoaded', () => {
    // Starfield
    const canvas = document.getElementById('starfield');
    if (canvas) new Starfield(canvas);

    setupHeaderScroll();
    setupMobileMenu();
    setupTarotFlip();
    setupScrollTop();
    setupCounters();
    setupCardAnimations();
    calculateMoonPhase();
    setupModalAndServices();
    setupDashboard();
    setupBentoVideos();

    // GDPR logic
    const gdprBanner = document.getElementById('gdpr-banner');
    const btnAccept = document.getElementById('gdpr-accept');
    const btnDecline = document.getElementById('gdpr-decline');

    if (gdprBanner && !localStorage.getItem('gdprConsent')) {
        setTimeout(() => {
            gdprBanner.classList.add('show');
        }, 1500); // Show after a slight delay
    }

    const hideBannerAndSave = (status) => {
        localStorage.setItem('gdprConsent', status);
        gdprBanner.classList.remove('show');
        gdprBanner.classList.add('hide');
    };

    if (btnAccept) {
        btnAccept.addEventListener('click', () => hideBannerAndSave('accepted'));
    }

    if (btnDecline) {
        btnDecline.addEventListener('click', () => hideBannerAndSave('declined'));
    }
});

// Video hover logic for Bento Cards
function setupBentoVideos() {
    const bentoCards = document.querySelectorAll('.bento-card');
    bentoCards.forEach(card => {
        const video = card.querySelector('.bento-video');
        if (video) {
            // Mouse Enter: Play video
            card.addEventListener('mouseenter', () => {
                video.currentTime = 0; // optional: restart
                video.play().catch(e => console.log('Video play interrupted:', e));
            });

            // Mouse Leave: Pause video
            card.addEventListener('mouseleave', () => {
                video.pause();
                video.currentTime = 0;
            });
        }
    });
}
