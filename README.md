# Imanzi Budget

A personal finance manager built with plain PHP and MySQL. It records every shilling or franc you receive and spend together with the reason behind it, then turns those records into reports, advice and decision support so you stay in control of your money.

There is no framework, no PDO and no build step. The application uses procedural PHP, the `mysqli` extension with prepared statements, hand-written CSS and a small amount of vanilla JavaScript. Icons are inline Heroicons.

## Contents

- [What it does](#what-it-does)
- [Screens at a glance](#screens-at-a-glance)
- [Technology](#technology)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Project structure](#project-structure)
- [Database design](#database-design)
- [How the intelligence works](#how-the-intelligence-works)
- [Security](#security)
- [Responsive design and themes](#responsive-design-and-themes)
- [Using the application](#using-the-application)

## What it does

**Record money in and money out**
Every transaction stores the amount, date, account, category, who paid you or who you paid, and, most importantly, why. Expenses can also be tagged as a need, a want or a saving, which feeds the advice engine.

**Keep accounts**
Track cash, mobile money, bank and savings accounts separately. Each account has an opening balance and a live balance calculated from its history. Money can be moved between accounts with a transfer that never counts as income or spending.

**Plan ahead**
Set monthly budgets per category, define savings goals with target dates, and keep a ledger of money you owe and money owed to you with partial payments and due dates.

**Understand where the money goes**
Reports cover any period: this month, last month, three or six months, the year or a custom range. They show income against spending, balance over time, spending by category with change against the previous period, income sources, needs against wants and the biggest transactions. Reports can be printed and every filtered list can be exported to CSV.

**Get advice**
An advisor page reads your records and produces plain-language guidance, a financial health score from 0 to 100, an emergency fund tracker and a 50/30/20 comparison.

**Make better decisions**
The decision helper answers the question "Can I afford this?" for a purchase paid in full or by instalments, explains the verdict and suggests a plan when the answer is not yet.

## Screens at a glance

| Page | Purpose |
| --- | --- |
| Sign in and Create account | Split-screen entry pages with validation, password strength meter and login throttling |
| Dashboard | Balance, income, spending and savings cards, six-month cash flow chart, health score, recent activity, category donut, accounts, budgets, upcoming dues and top advice |
| Transactions | Searchable, filterable, paginated ledger with CSV export, plus a single form for income, expense and transfer |
| Accounts | Balances per account, add, edit, archive, restore and delete |
| Categories | Income and expense categories with colours and monthly totals |
| Budgets | Monthly limits with progress, remaining amount and daily allowance |
| Savings goals | Progress, required monthly saving, add and withdraw funds |
| Debts and loans | Money you owe and money owed to you, with payments and overdue tracking |
| Reports | Period reports with charts, comparisons, print and CSV |
| Advisor | Health score, ranked advice, emergency fund and 50/30/20 check |
| Decision helper | Affordability verdict with reasons, numbers used and history |
| Settings | Profile, currency, savings target, emergency fund target and password change |

## Technology

| Layer | Choice |
| --- | --- |
| Language | PHP 8.1 or newer, no framework |
| Database | MySQL 8 or newer, accessed with `mysqli` prepared statements |
| Front end | Hand-written CSS with custom properties, vanilla JavaScript |
| Charts | Server-rendered SVG, no chart library |
| Icons | Heroicons (outline set), inlined as SVG |
| Font | Plus Jakarta Sans from Google Fonts with system font fallbacks |

## Requirements

- PHP 8.1 or newer with the `mysqli` and `mbstring` extensions enabled
- MySQL 8.0 or newer (MariaDB 10.5 or newer also works)
- Any web server that can run PHP, or the PHP built-in server for local use

## Installation

**1. Get the code**

```bash
git clone https://github.com/stevenimanzi/personal_financial_managment_system.git
cd personal_financial_managment_system
```

**2. Create the configuration file**

```bash
cp config/config.example.php config/config.php
```

On Windows PowerShell use `Copy-Item config\config.example.php config\config.php`. Open `config/config.php` and set the MySQL user and password.

**3. Create the database and tables**

```bash
php database/install.php
```

The script creates the `imanzi_budget` database and every table. It can be run again safely because it only creates what is missing. If you prefer, import `database/schema.sql` with any MySQL client instead.

**4. Start the application**

For local use, the PHP built-in server is enough:

```bash
php -S localhost:8080
```

Then open `http://localhost:8080` and create your account. Default accounts (cash, mobile money, bank) and a set of income and expense categories are created for you on registration.

To host it on Apache, point the document root at the project folder. The included `.htaccess` blocks direct access to the `config`, `includes` and `database` folders and disables directory listing. On Nginx, add an equivalent `deny` rule for those folders.

## Configuration

All settings live in `config/config.php`.

| Constant | Meaning |
| --- | --- |
| `DB_HOST`, `DB_PORT` | MySQL server address |
| `DB_USER`, `DB_PASS` | MySQL credentials |
| `DB_NAME` | Database name, `imanzi_budget` by default |
| `APP_NAME` | Name shown in the sidebar and page titles |
| `APP_TIMEZONE` | Timezone used for dates and "this month" calculations |
| `PER_PAGE` | Rows per page in the transaction list |
| `LOGIN_MAX_ATTEMPTS` | Failed sign-ins allowed inside the window |
| `LOGIN_WINDOW_MINUTES` | Length of the throttling window |

Personal preferences are set per user in the Settings page: currency, savings target as a percentage of income, and the emergency fund target in months of expenses. Fourteen currencies are supported. Changing the currency changes how amounts are displayed and does not convert stored values.

`config/config.php` is excluded from version control so credentials are never committed.

## Project structure

```
.
├── index.php               Redirects to the dashboard or the sign-in page
├── login.php               Sign in
├── register.php            Create account
├── logout.php              Sign out
├── dashboard.php           Overview
├── transactions.php        Ledger, filters, CSV export, delete
├── transaction.php         Add and edit income, expense and transfer
├── accounts.php            Accounts
├── categories.php          Categories
├── budgets.php             Monthly budgets
├── goals.php               Savings goals
├── debts.php               Debts and loans
├── reports.php             Period reports
├── advisor.php             Advice and health score
├── decisions.php           Affordability checker
├── profile.php             Settings and password
├── assets/
│   ├── css/app.css         Design system and responsive rules
│   └── js/app.js           Sidebar, theme, dialogs, confirmations, form behaviour
├── config/
│   └── config.example.php  Configuration template
├── database/
│   ├── schema.sql          Database definition
│   └── install.php         Command line installer
└── includes/
    ├── bootstrap.php       Session, configuration, error handling
    ├── db.php              mysqli helpers with prepared statements
    ├── helpers.php         Formatting, validation, CSRF, flash messages, icons
    ├── auth.php            Authentication and login throttling
    ├── analytics.php       Totals, insights, health score, decision logic
    ├── charts.php          SVG bar, line, donut and ring charts
    ├── layout.php          Sidebar, top bar, dialogs and shared partials
    ├── auth_layout.php     Sign-in and registration layout
    └── icon_paths.php      Heroicons path data
```

## Database design

All data belongs to a user, and every query is filtered by the signed-in user.

| Table | Contents |
| --- | --- |
| `users` | Profile, password hash, currency, savings target and emergency fund target |
| `login_attempts` | Failed sign-ins used for throttling |
| `accounts` | Cash, bank, mobile money and savings accounts with opening balance |
| `categories` | Income and expense categories with colours |
| `transactions` | Income, expense and transfer records with party, reason and need or want tag |
| `budgets` | Monthly limit per expense category |
| `goals` | Savings goals with target, saved amount and date |
| `debts` | Money owed in either direction with partial payments |
| `decisions` | History of affordability checks |

Account balance is the opening balance, plus income, minus expenses and outgoing transfers, plus incoming transfers. Deleting a user removes all of their records. Deleting a category keeps its transactions and marks them uncategorised.

## How the intelligence works

The advisor is rule based and fully explainable. Every statement it makes can be traced to figures in your own records.

**Typical month.** Income and spending averages come from up to the last three complete months that contain data. When no history exists yet, the current month is used.

**Advice.** Each rule produces an item ranked as urgent, to watch, informational or going well:

- Savings rate against your target, and spending greater than income
- Spending pace for the month compared with your usual level
- Categories running well above their normal share
- Budgets that are exceeded or nearly used, with a daily allowance for the rest of the month
- Cash reserve measured in months of expenses against your emergency target
- Share of income going to wants and to needs
- Debts that are overdue or due within seven days, and people who are late paying you
- Goals that need more than your average monthly surplus
- Single expenses that take a large share of income
- Gaps in record keeping

**Health score.** The score from 0 to 100 combines four parts:

| Part | Weight | Measured by |
| --- | --- | --- |
| Savings rate | 35 | Rate against your savings target |
| Cash reserve | 25 | Months of expenses covered against your emergency target |
| Budget discipline | 20 | Share of budgets that are not exceeded |
| Debt health | 20 | Money owed against income, with a penalty for overdue payments |

**Decision helper.** The check starts from 100 and subtracts for each risk:

- The cost exceeds the money you hold, or leaves less than one month of expenses, or dips below your emergency cushion
- The price is a large share of a normal month of income
- You have no monthly surplus, or an instalment would use most of it
- You still owe debts while buying a want

Essential purchases are treated more leniently. A score of 70 or more means go ahead, 40 to 69 means wait and below 40 means avoid. When the answer is not yes, the helper estimates how many months of saving would let you buy without touching your emergency cushion.

## Security

- All database access uses `mysqli` prepared statements
- Passwords are hashed with `password_hash` and verified with `password_verify`
- Every form carries a CSRF token that is verified on submission
- Sessions use HttpOnly and SameSite cookies, and the session id is regenerated at sign-in
- Sign-in attempts are throttled per email address and per IP address
- Output is escaped with `htmlspecialchars`
- Every record is scoped to its owner, so one user can never read or change another user's data
- Destructive actions are POST only and ask for confirmation

## Responsive design and themes

The layout adapts from large desktops down to small phones. On narrow screens the sidebar becomes a slide-in menu, tables turn into stacked cards and forms switch to a single column. A light and a dark theme are available from the top bar and the choice is remembered in the browser. Reports have a print stylesheet that hides the navigation.

## Using the application

1. Register and choose your currency.
2. Open Accounts and enter the opening balance of each account.
3. Record transactions as they happen. Always fill in the reason, because it makes your history meaningful.
4. Create budgets for the categories you want to control.
5. Add savings goals and record any debts.
6. Review the dashboard daily, and open Reports and Advisor at the end of each week or month.
7. Before a significant purchase, run it through the Decision helper.
