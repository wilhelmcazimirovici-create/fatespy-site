# FateSpy Development Server

## Pornire server PHP pentru dezvoltare

Proiectul FateSpy folosește PHP backend, deci ai nevoie de un server PHP pentru a rula aplicația local.

### Metoda 1: Folosește script-ul batch (Windows)

Dublu-click pe:
```
start-dev-server.bat
```

### Metoda 2: Folosește npm

```bash
npm run dev:php
```

### Metoda 3: Rulează manual

```bash
php -S localhost:3000
```

## Acces aplicație

După pornirea serverului, deschide în browser:

- **Homepage:** http://localhost:3000/
- **Login:** http://localhost:3000/login.html
- **Admin:** http://localhost:3000/admin.html
- **User Dashboard:** http://localhost:3000/user-dashboard.html

## Oprire server

Apasă **Ctrl+C** în terminalul unde rulează serverul.

## Note importante

- ✅ **Folosește `npm run dev:php`** pentru backend (PHP APIs)
- ⚠️ **NU folosi `npm run dev`** (Vite) - acesta nu procesează PHP
- 📦 Folosește `npm run build` doar pentru a compila assets-urile frontend

## Troubleshooting

### "php is not recognized"
PHP nu este instalat sau nu este în PATH. Instalează PHP de la:
- https://windows.php.net/download/

### "Address already in use"
Portul 3000 este ocupat. Folosește alt port:
```bash
php -S localhost:8000
```

### "Could not load Google Client ID: SyntaxError"
Folosești Vite în loc de PHP server. Rulează `npm run dev:php` în loc de `npm run dev`.
