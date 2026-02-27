# FateSpy - Project Handoff Document

## 1. Project Overview
**FateSpy** is a mystic intelligence platform that combines ancient wisdom (Astrology, Tarot, Numerology, Palmistry) with modern AI models. The application provides daily/weekly/monthly horoscopes, personalized birth charts, and AI-powered divination services (Palm reading, Aura scanning, Coffee cup reading, Tarot). 

The website is set to be hosted on standard shared hosting (**hostico.ro**).

## 2. Technology Stack
- **Frontend**: Vanilla HTML5, CSS3, JavaScript. No heavy frameworks (React/Vue/Tailwind) are used in the main public templates to maintain high performance and simple standard hosting compatibility. Premium UI/UX features glassmorphism, floating animations, and starry backgrounds.
- **Backend**: PHP 8.x (RESTful API endpoints returning JSON).
- **Database**: MySQL. Database migrations and initial schema setup are handled via `install.php`.

## 3. What Has Been Completed So Far
1. **Frontend UI/UX (Public Pages)**:
   - `index.html`: Contains general horoscopes, complex UI animations, responsive bento grids, and the main entry points for AI readings.
   - `personal-horoscope.html`: Added a visually distinct section for generating personalized horoscopes based on specific birth data (Date, Time, Location, Gender).

2. **Unified Authentication System (`login.html`)**:
   - Completely rewritten to feature a premium, mobile-responsive dark theme with floating stars and a glassmorphism card.
   - Features: Login, Registration (with password strength meter), Forgot Password, and 2FA input screens.
   - **Google OAuth Integration**: Added "Continue with Google" securely tied to `api/auth/google.php`.
   - **Role-based Redirection**: A single login page handles all users. Upon successful login (or if already logged in), JS checks the role in `localStorage` (`fs_user`) and dynamically redirects `admin` users to `/admin.html` and regular users to `/user-dashboard.html`.

3. **User Dashboard (`user-dashboard.html`)**:
   - Built the complete frontend UI for the user portal.
   - Features tabs for: Overview (stats and CTA to upgrade), Services Store (purchasing AI Tarot, Aura scan, etc.), My Images (grid UI for uploading and managing Left/Right palm, Face, and Coffee Cup photos for AI analysis), History (view past readings), and Profile settings.

4. **Admin Panel (`admin.html`)**:
   - Built the frontend UI for administrative tasks.
   - Includes layout sections for managing Users, configuring multiple **AI API Keys** (OpenAI, Gemini, Grok, Minimax, etc.), managing email templates with dynamic fields, and general platform settings.
   - Includes frontend Auth Guard: immediately kicks unauthenticated users back to `login.html`.
   - Features a functional secure logout.

5. **Branding Consistency**:
   - Swept all functional pages (`login.html`, `admin.html`, `user-dashboard.html`, `api.html`, `contact.html`, `privacy.html`, `cookies.html`, `terms.html`) to ensure the exact same visual logo (`assets/images/logo.webp` alongside `FateSpy.com` text using the Cinzel font) is used instead of abstract emojis.

## 4. Pending / Next Steps for the Next AI Agent

1. **Backend Integration for the User Dashboard**:
   - Implement the PHP backend for uploading and securely storing user images (Palms, Face, Coffee Cup).
   - Build the backend logic that connects these stored images to the AI models to generate the readings.
   
2. **Backend Integration for the Admin Panel**:
   - Connect the AI API Keys frontend UI to securely encrypt and store these keys in the database.
   - Wire up the Email Templates section so the admin can write and save standardized emails.

3. **AI Generation & Cron Jobs**:
   - The user requested that general zodiac sign horoscopes should be generated *once per period* (daily, weekly, monthly, yearly) using AI and stored in the database, instead of making real-time API calls for every visitor. This needs a PHP script that can be executed via a Cron job.

4. **Payments / VIP Subscriptions**:
   - `user-dashboard.html` features numerous "Upgrade to VIP" and "Buy Now" CTAs. Integrate a payment processor (e.g., Stripe) securely on the PHP backend to handle one-off reading purchases and VIP subscriptions.

5. **Database Structure Verification**:
   - Make sure `install.php` is fully updated with all the tables needed for the new features (e.g., `user_images`, `user_services`, `api_keys`, `email_templates`).

## Important Context
- **Design Philosophy**: The user highly values premium, "wow-factor" aesthetics. Do not introduce basic, unstyled elements. Always maintain the dark mystic theme, animations, and typography choices (Inter & Cinzel).
- **Security**: The platform handles personal data and payments. Keep a strong focus on security (SQL injection prevention, secure file uploads, strong auth checks).
