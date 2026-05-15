---

## Wizard Steps (setup.php)
- Step 1: Business type
- Step 2: Restaurant type (fast_food, casual_dining, premium_dining, cloud_kitchen)
- Step 3: Seat count (number input) + Area in sqm (used for AC calculation)
- Step 4: Budget (range cards: Under 500k / 500k-1.5M / 1.5M-3M / 3M+) in EGP
- Step 5: Location (city dropdown)
- Step 6: Installation services (intent only — POS, Electrical, Network, AC, Kitchen Setup)
- Step 7: Staffing (waiter, chef, cashier, security, kitchen helpers — with quantity per role)

### Important Rules for Wizard
- Step 6 collects INTENT only — no pricing, no company selection
- Step 7 collects staff quantities per role (not just yes/no)
- Jobs are NOT created here — only after successful payment
- No tier selection in wizard — tier is auto-derived in packages.php
- Session keys: installation_services, labor (map of role → qty), area_sqm, ac_units

---

## Recommendation Engine (packages.php)
- Tier auto-derived from budget allocation:
  - ratio >= 0.35 → Premium
  - ratio >= 0.20 → Balanced
  - else → Starter
- Modules auto-generated based on restaurant type
- AC units calculated from area: ceil(area_sqm / 40), minimum 1
- ac_units stored in session and saved to installation_data in order
- Never remove existing cart logic

---

## Three Services Logic

### 1. Products
- Budget ceiling controls recommendations
- Cart built in packages.php
- Paid via Paymob

### 2. Labor (individuals)
- provider_type: waiter, chef, barista, cashier, cleaner
- Separate platform with own dashboard
- Jobs created after payment using staffing quantities from wizard Step 7
- Commission based
- Bidding does NOT apply to labor
- Seed labor workers: user IDs 67-76 — NEVER touch these

### 3. Installation Companies
- user_type 'company' added to user_type enum in PostgreSQL
- Companies have quote model (NOT bidding)
- Company signup: Labor/company_signup.php
- Company dashboard: Labor/company_dashboard.php
- Login: auth/login.php handles case "company" → redirects to Labor/company_dashboard.php
- Flow:
  1. Wizard Step 6 → user selects services, saved as installation_services in session
  2. place_order.php → saves installation_data to orders table (includes ac_units + area_sqm)
  3. After payment → paymob_callback.php creates one installation_requests row per service
  4. Business sees ALL matching companies upfront on service_jobs.php (even before quotes)
  5. Companies see open requests on company dashboard → submit quote (price + message + website)
  6. Business sees actual quote on company card → accepts one
  7. Accepted quote → installation_requests.company_id set, status = accepted
  8. All other quotes for that request → status = rejected
- Seed company users: IDs 139-142 + 146-147, password is 'password'
  - 139: TechPOS Solutions (pos)
  - 140: ElectroPro Egypt (electrical)
  - 141: NetSetup Pro (network)
  - 142: CoolAir Installations (ac)
  - 146: Garrana Group (kitchen)
  - 147: EMAJ Egypt (kitchen)

---

## AC Pricing Logic
- AC units = ceil(area_sqm / 40), minimum 1
- Installation rate per unit depends on tonnage:
  - 1.5 ton → 700 EGP per unit (confirmed)
  - Other tonnages → TBD
- Platform auto-calculates suggested price = rate × units
- Suggested price shown to company as pre-filled quote amount
- Company can adjust and submit
- Before quote: service_jobs.php shows starting_from × ac_units
- After quote: shows actual submitted price
- AC products not yet in DB — tonnage derivation pending product seeding
- starting_from for CoolAir = 600 EGP per unit

---

## Database Structure

### users table
- id, name, email, password_hash, user_type, phone, country, city, street, status, created_at
- user_type enum values: admin, business, customer, labor, vendor, company

### labors table (individuals only — NO technicians)
- user_id, national_id, dob, skills, experience_level, availability_status
- military_status, hourly_rate, avg_rating, profile_picture, status
- name, provider_type, balance, labor_role
- provider_type values: waiter, chef, barista, cashier, cleaner (NO technician)

### companies table
- company_id, user_id, company_name, description, services (TEXT[])
- base_price, avg_rating, established_year, company_size (small/medium/large)
- logo, availability_status, status, location
- website (VARCHAR 255)
- starting_from (INT) — per unit price for AC, flat for others

### jobs table
- job_id, business_id, title, description, location, budget, status
- created_at, price, worker_id, job_type, company_id
- job_type values: labor

### orders table
- id, status, customer_user_id, business_user_id, service_fees, order_total
- delivery_location, payment_status, paid_at, payment_reference
- preferred_delivery_date, payment_method
- labor_data (JSONB) — map of role → quantity
- installation_data (JSONB) — includes services array + ac_units + area_sqm

### installation_requests table
- request_id, user_id, company_id, services (TEXT[])
- status: pending → accepted → completed
- total_price, created_at

### installation_quotes table
- quote_id, request_id, company_id
- price (NUMERIC 10,2)
- message (TEXT)
- website_link (VARCHAR 255)
- status: pending → accepted → rejected
- created_at

---

## UI/Design Decisions

### Colors
- Primary blue: #185FA5 (buttons, selected states, active highlights)
- No teal/turquoise anywhere
- Selected card state: border #185FA5, background #f0f6ff
- All buttons: squared (no heavy border-radius)

### Step 3 — Size + Area
- Two seat cards: Indoor + Outdoor
- One area card: Restaurant Area in m²
- Area presets: 30, 50, 80, 120, 200, 300 m²
- Default area: 50 m²

### Step 6 — Installation Cards
- 5 cards: POS System, Electrical Wiring, Network & WiFi, AC Installation, Kitchen Setup
- Click to select/deselect
- Selected: blue border + blue check icon
- Info note: "These services are fulfilled by verified local companies"

### Step 7 — Staffing Rows
- 5 roles: Waiters, Chefs, Cashiers, Security, Kitchen Helpers
- Each row: icon + name + description + minus/number/plus counter
- All start at 0, set to 0 to skip
- Active row (count > 0): #185FA5 border highlight

### service_jobs.php — Installation Section
- Shows all matching companies as cards per service (even before quotes)
- Before quote: shows starting_from × ac_units for AC, flat starting_from for others
- After quote: shows actual price + message + Accept Quote button
- After accepted: card highlighted with blue border, "Accepted" badge
- Other companies after acceptance: "Not Selected" badge

---

## Security
- Paymob keys in config.php (gitignored)
- HMAC verification rejects with exit
- success.php has ownership check — uses session restore if session lost on redirect
- paymob_callback.php has NO session_start() — server to server only
- place_order.php determines business vs customer from $_SESSION["user_type"]

---

## Fixed Bugs
- success.php Unauthorized — fixed by restoring session from order if lost on ngrok redirect
- paymob_callback.php logging out user — fixed by removing session_start()
- place_order.php wrong business_user_id — fixed by using session user_type instead of DB check

---

## Git Branch Strategy
- Always create a new branch before Claude Code works
- Never commit directly to main
- Commit before every Claude Code session

---

## What NOT to Touch
- Never remove existing cart logic in packages.php
- Never touch labor worker records (IDs 67-76) in labors table
- Never touch bids table structure
- Never touch company seed users (IDs 139-142, 146-147)
- Always work one change at a time
- Test full flow after every change: wizard → packages → order summary → payment

---

## TODO Before Launch
- AC products need to be added to DB under ambience module with tonnage in specs
- AC installation rates per tonnage need to be finalized (1.5 ton = 700 EGP confirmed)
- installation_data in orders needs to store ac_units + area_sqm
- Company dashboard needs to show ac_units to help CoolAir price the quote
- starting_from prices in companies table — set real values (done for seed companies)
- Add item count from order to company dashboard so companies can price better
- Commission calculation for companies
- Company rating system
- ambience module needs to be opened for all restaurant types (currently premium only)

## Real Price Ranges (Egypt 2025)
### POS Installation
- Full setup (software + hardware config + training): 5,000 EGP starting
- TechPOS Solutions starting_from = 5,000 EGP

### AC Installation
- Per unit installation rate: 600 EGP starting_from (CoolAir)
- 1.5 ton unit = 700 EGP installation confirmed
- Other tonnages TBD
- Formula: rate × ac_units = suggested quote price

### Electrical
- ElectroPro Egypt starting_from = 2,500 EGP

### Network
- NetSetup Pro starting_from = 3,000 EGP

### Kitchen
- Garrana Group starting_from = 5,000 EGP
- EMAJ Egypt starting_from = 4,500 EGP

---

## Future Features (after presentation)
- 3D wizard experience using Three.js
- Cuisine type as profile field
- Module weights table in database
- Product requirements table in database
- Returning user flow from dashboard
- Company settings page (update website, starting_from price)
- Company rating system
- User decline flow → match to next company
- AC tonnage-based dynamic pricing fully implemented

## AC Installation Logic (implemented)
- Formula: ac_units = ceil(area_sqm / 40), min 1
- Tonnage derived from area_sqm / ac_units:
  - ≤ 20 m² per unit → 1.5 ton
  - ≤ 30 m² per unit → 2 ton
  - ≤ 45 m² per unit → 2.5 ton
  - > 45 m² per unit → 3 ton
- Per-company rates stored in company_ac_rates table
- service_jobs.php reads area_sqm from orders.installation_data (not session)
- installation_data now saves: { services, area_sqm, ac_units }
- paymob_callback.php reads installation_data["services"] with fallback for old format

## New Company
- Future Air (user_id = X, company_id = 15) — AC only, Giza
- Rates in company_ac_rates table

## Bugs Fixed
- success.php Unauthorized — now restores session from order instead of dying
- paymob_callback.php installation_data parsing — handles new JSON format
- merchant_order_id now includes timestamp to prevent Paymob caching

## Parked for Later (do not implement until DB products exist)

### Dining / Tables
- Table size ratio per restaurant type:
  - Fast Food: 4-seater 50%, 2-seater 30%, 6-seater 20% + bar seating option
  - Standard Dining: 4-seater 50%, 2-seater 25%, 6-seater 20%, 10-seater 5%
  - Premium Dining: 4-seater 45%, 2-seater 20%, 8-seater 25%, 12-seater 10%
- Requires these products in DB first: 2-seater, 8-seater, 10-seater, 12-seater dining sets
- Bar seating option for fast food only
- After products added → implement ratio recommendation in packages.php

### Budget
- Installation cost estimate included in total wizard budget
- Real installation pricing per service type (AC per unit, POS per terminal, kitchen setup, infrastructure)

### Furniture
- TV mounting service
- Furniture assembly service

### Products / Recommendation Engine
- Restaurant type affecting specific kitchen product recommendations
- Cloud kitchen should not get premium combi oven
- Fast food should not get luxury dining sets

### Wizard
- 3D table layout visualization (Three.js)
- Ambience module for all restaurant types (currently premium only)