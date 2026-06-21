# System Zarządzania Serwisem Elektroniki - ElectroService

Kompleksowa aplikacja webowa przeznaczona do zarządzania profesjonalnym serwisem sprzętu elektronicznego, ze szczególnym uwzględnieniem marki Apple. System automatyzuje proces od przyjęcia urządzenia, przez diagnostykę i logistykę części, aż po kontrolę jakości i wydanie sprzętu klientowi.

**Jaki problem rozwiązuje?**
W małych i średnich serwisach komunikacja między biurem (recepcją), technikami a magazynem często kuleje. ElectroService eliminuje ten problem, wprowadzając sztywne reguły workflow, automatyczne powiadomienia o statusach oraz dynamiczne zarządzanie terminami, co pozwala uniknąć przestojów i przepełnienia warsztatu.

**Czym się wyróżnia?**
- **Apple-Centric:** Predefiniowany katalog modeli i części dedykowany urządzeniom Apple.
- **Smart Logistics:** Automatyczne powiązanie zapotrzebowania na części z konkretnym zleceniem i modelem.
- **Workflow Safety:** Mechanizmy blokujące (np. limit aktywnych zleceń na technika, blokada wydania bez części).
- **Rollback Logic:** Zaawansowana obsługa odrzucenia kosztów przez klienta, przywracająca zlecenie do poprawki z najwyższym priorytetem.

---

## Uruchomienie projektu (developer)

### Użyte technologie

| Technologia | Wersja | Link |
|---|---|---|
| PHP | ^8.3 | https://www.php.net |
| Laravel | ^13.8 | https://laravel.com |
| SQLite / MySQL | 3.0+ / 8.0+ | https://www.sqlite.org |
| Composer | 2.x | https://getcomposer.org |
| Bootstrap | 5.3 | https://getbootstrap.com |
| FullCalendar | 6.x | https://fullcalendar.io |

### Wymagania programowe

- **System operacyjny:** Windows 10/11, macOS lub Linux.
- **PHP ^8.3** z rozszerzeniami: `pdo_sqlite`, `mbstring`, `openssl`, `curl`.
- **Composer 2** — menedżer zależności PHP.
- **SQLite** (domyślnie skonfigurowane w `database.sqlite`).

### Proces instalacji

**1. Sklonuj repozytorium**
```bash
git clone https://github.com/P0z528/ProjektSerwis.git
cd ProjektSerwis
```

**2. Zainstaluj zależności**
```bash
composer install
```

### Proces konfiguracji

**1. Zmienne środowiskowe**
Skopiuj plik `.env.example` jako `.env`. Domyślna konfiguracja używa bazy SQLite.
```bash
cp .env.example .env
php artisan key:generate
```

**2. Baza danych**
Utwórz plik bazy danych (jeśli używasz SQLite) i wykonaj migracje wraz z danymi początkowymi:
```bash
touch database/database.sqlite
php artisan migrate --seed
```

**3. Dane logowania (Seed)**
System generuje domyślne konta dla każdej roli (hasło dla wszystkich: `password`):
- **Admin:** `admin`
- **Recepcja:** `recepcja`
- **Technik:** `technik`
- **Magazyn:** `magazyn`

**4. Uruchomienie serwera**
Wymagany jest jeden terminal:
```bash
# Terminal 1: Serwer PHP
php artisan serve
```
Aplikacja będzie dostępna pod adresem: **http://localhost:8000**

---

## Uruchomienie projektu (user)

ElectroService jest aplikacją webową typu SaaS. Użytkownik końcowy (pracownik serwisu) korzysta z niej za pomocą nowoczesnej przeglądarki internetowej (Chrome, Firefox, Edge, Safari).

**Wymagania sprzętowe:**
- Dowolny komputer/tablet z dostępem do internetu.
- Rekomendowana rozdzielczość ekranu: min. 1280x720 px (pełna responsywność).
- Przeglądarka z obsługą JavaScript i HTML5 (do kompresji zdjęć w locie).

---

## Podręcznik użytkownika

### Role w systemie i moduły

| Rola | Odpowiedzialność | Kluczowe funkcje |
|---|---|---|
| **Administrator** | Zarządzanie zasobami ludzkimi i procesem | Zarządzanie pracownikami, edycja danych klientów i statusów, kontrola jakości (QA), statystyki KPI. |
| **Recepcja** | Kontakt z klientem i logistyka przyjęć | Dynamiczny kalendarz, tworzenie zleceń, wydruki potwierdzeń .txt, wydawanie sprzętu. |
| **Technik** | Serwis fizyczny urządzeń | Pobieranie zleceń (max 4), zamawianie części, raportowanie napraw. |
| **Magazyn** | Gospodarka częściami | Obsługa zapotrzebowań, księgowanie dostaw, kontrola stanów magazynowych. |

### 1. Panel Recepcji — Przyjęcie zlecenia
Głównym zadaniem recepcji jest sprawne wprowadzenie klienta do systemu.

- **Dynamiczny Kalendarz:** System automatycznie oblicza limity przyjęć:
  - Dni robocze: **13 urządzeń**.
  - Soboty: **10 urządzeń**.
  - Niedziele: **Nieczynne** (blokada wyboru).
- **Walidacja danych:** System normalizuje numery kierunkowe (domyślnie +48) i sprawdza poprawność numeru telefonu (9 cyfr dla PL).
- **Katalog Apple:** Wybór modelu (np. iPhone 15 Pro) automatycznie filtruje dostępne części i usługi w cenniku.
- **Potwierdzenie .txt:** Po zapisaniu zlecenia generowany jest dokument `Zlecenie_{ID}.txt` gotowy do wydruku dla klienta.

### 2. Panel Technika — Warsztat
Technik operuje na "puli wspólnej" zleceń o statusie "W kolejce".

- **Limit obciążenia:** Jeden technik może mieć przypisane maksymalnie **4 aktywne zlecenia**. Próba wzięcia kolejnego skutkuje blokadą.
- **Sortowanie Priorytetowe:** Na górze listy zawsze pojawiają się zlecenia odrzucone przez QA (Poprawka) oraz te z "Rollbackiem" od klienta.
- **Zamawianie części:** Technik może zgłosić zapotrzebowanie na części z katalogu. Jeśli część nie była przewidziana przy przyjęciu, jej koszt jest automatycznie doliczany do zlecenia.
- **Blokada zamykania:** Nie można oznaczyć naprawy jako "Gotowa", dopóki magazyn nie wyda wszystkich zamówionych części.

### 3. Panel Magazynu — Logistyka
Magazynier widzi listę zapotrzebowań zgłoszonych przez techników.

- **Statusy części:** `Oczekuje` (jest na stanie), `Do zamówienia` (brak na stanie), `Wydano`.
- **Księgowanie dostaw:** Jedno kliknięcie przenosi wszystkie pozycje z "Listy zakupów" na stan magazynowy, odblokowując możliwość wydania ich technikom.

### 4. Panel Administratora — Zarządzanie i QA
Administrator posiada najwyższe uprawnienia w systemie, czuwając nad poprawnością obiegu dokumentów.

- **Zarządzanie pracownikami:** Możliwość dodawania nowych kont, edycji danych istniejących pracowników oraz ich bezpiecznego usuwania (z zachowaniem ciągłości zleceń).
- **Zarządzanie klientami:** Pełny wgląd w listę klientów i przypisanych do nich urządzeń. Admin może ręcznie korygować dane kontaktowe oraz wymuszać zmiany statusów zleceń w sytuacjach wyjątkowych.
- **Kontrola Jakości (QA):** Zatwierdzanie napraw ukończonych przez techników lub odsyłanie ich do poprawki z komentarzem.

---

### Ścieżki użytkownika (User flow)

Pełny obieg urządzenia w systemie ElectroService:
1. **Przyjęcie (Recepcja):** Wprowadzenie danych klienta i urządzenia, wybór terminu w kalendarzu, wykonanie dokumentacji fotograficznej i wydruk potwierdzenia dla klienta.
2. **Diagnostyka i Naprawa (Technik):** Pobranie zlecenia z puli, analiza usterki. Jeśli potrzebne są dodatkowe elementy – zgłoszenie zapotrzebowania do magazynu.
3. **Obsługa części (Magazyn):** Weryfikacja stanu magazynowego. Jeśli części brak – dodanie do listy zakupów. Po dostawie – wydanie części technikowi.
4. **Zakończenie prac (Technik):** Po zamontowaniu części technik oznacza zlecenie jako ukończone.
5. **Kontrola (Administrator):** Weryfikacja poprawności naprawy. Zatwierdzenie (status "Gotowe") lub odrzucenie (status "Poprawka").
6. **Wydanie (Recepcja):** Rozliczenie końcowe z klientem (akceptacja ewentualnych zmian kosztów) i zmiana statusu na "Wydane".

---

### Struktura bazy danych

Najważniejsze tabele systemu:

| Tabela | Opis |
|---|---|
| `Uzytkownicy` | Konta pracowników (login, rola, hash hasła). |
| `Klienci` | Dane kontaktowe zleceniodawców. |
| `Urzadzenia` | Informacje o sprzęcie (model, numer seryjny, przypisany klient). |
| `Zlecenia` | Centralna tabela workflow (statusy, koszty, daty naprawy, przypisany technik). |
| `ModeleApple` | Słownik typów i modeli urządzeń. |
| `CzesciKatalog` | Cennik usług i części przypisany do modeli. |
| `Czesci` | Aktualny stan magazynowy (ilości). |
| `Zapotrzebowania` | Historia i status zamówień części dla konkretnych zleceń. |
| `ZdjeciaZlecen` | Dokumentacja wizualna sprzętu. |

---

### Przypadki brzegowe i walidacja

System posiada szereg zabezpieczeń gwarantujących spójność danych:
- **Walidacja telefonu:** Przymusowe formatowanie numeru kierunkowego (np. +48) oraz weryfikacja długości numeru (9 cyfr dla Polski).
- **Bezpieczeństwo ról:** Blokada usunięcia ostatniego pracownika z danej roli (system zawsze musi mieć min. 1 Admina, 1 Technika itd.).
- **Ochrona Magazynu:** Zapobieganie powstawaniu duplikatów zapotrzebowań dla tego samego zlecenia.
- **Multimedia:** Front-endowa walidacja formatów zdjęć oraz ich automatyczna kompresja przed wysyłką (oszczędność miejsca na serwerze).
- **Ciągłość pracy:** Automatyczne przekazywanie zleceń do "Sierocej puli" w przypadku modyfikacji konta przypisanego technika.

---

### Responsywność i UI
Interfejs został zbudowany w oparciu o framework **Bootstrap 5**, co zapewnia pełną responsywność. Dzięki zastosowaniu klas takich jak `table-responsive`, wszystkie zestawienia danych, formularze oraz panele sterowania są czytelne zarówno na monitorach desktopowych, jak i na urządzeniach mobilnych (tablety, smartfony).

---

### 5. Zaawansowane mechanizmy biznesowe

- **Mechanizm Rollback (Odrzucenie kosztów):** Jeśli podczas wydawania sprzętu klient nie zaakceptuje zwiększonych kosztów (wynikających z dodatkowych części zamówionych przez technika), recepcja klika "Odrzuca". Zlecenie natychmiast wraca do TEGO SAMEGO technika jako **priorytet** w celu wymontowania nowej części i przywrócenia stanu pierwotnego.
- **Sieroca Pula:** W przypadku usunięcia konta technika lub zmiany jego roli na inną, wszystkie jego aktywne zlecenia automatycznie tracą przypisanie i wracają do puli głównej (status "W kolejce"), aby inni pracownicy mogli je przejąć.
- **Bezpieczeństwo danych i obrazów:** 
  - Zdjęcia dokumentujące stan sprzętu są **kompresowane w przeglądarce** (JS Canvas) przed wysyłką na serwer, co oszczędza transfer i miejsce na dysku.
  - Hasła pracowników są chronione algorytmem **BCRYPT**.

---

## Plany rozbudowy

**Wersja 2.0:**
- **Moduł Finansowy:** Generowanie faktur PDF (zamiast .txt) oraz integracja z systemami płatności online.
- **Powiadomienia:** Automatyczna wysyłka SMS/E-mail do klienta po zmianie statusu na "Gotowe".
- **Panel Klienta:** Możliwość sprawdzenia statusu online po wpisaniu numeru seryjnego i ID zlecenia (obecnie dostępne tylko z poziomu recepcji).
- **Logi Aktywności:** Rejestrowanie każdej zmiany statusu z informacją, który pracownik jej dokonał.

**Optymalizacja:**
- Migracja bazy danych na PostgreSQL w celu zwiększenia wydajności przy dużym ruchu.
- Wdrożenie cache'owania Redis dla dynamicznego kalendarza dostępności.
- Konteneryzacja (Docker) dla ułatwienia wdrożeń produkcyjnych.

---
*Projekt: System Zarządzania Serwisem Elektroniki*
*Autor: Mateusz Pociecha*
*Status: Academic Project / Senior Laravel Developer Approach*
