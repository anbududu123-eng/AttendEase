# AttendEase — Student Attendance and Shortage Calculator

A professional, full-stack academic attendance management and regulatory compliance web application built strictly with **HTML5, Pure CSS (authored in SCSS), PHP 8+, and MySQL (PDO)**.

---

## 🌟 Key Features

1. **Role-Based Authentication (Student & Administrator)**:
   - Secure registration with client and server-side validation.
   - Passwords hashed with `password_hash()` (BCrypt) and authenticated via `password_verify()`.
   - Protection against CSRF attacks, SQL injection via prepared statements, and XSS prevention via `htmlspecialchars()`.
   - Pure PHP sessions with automatic session regeneration upon login.

2. **Student Dashboard**:
   - Academic profile header (Register Number, Department, Semester, Year of Study).
   - 5 KPI statistic cards: Overall Attendance %, Total Conducted, Total Attended, Safe Subjects, Shortage Subjects.
   - Interactive subject summary table with pure HTML/CSS progress bars and status badges (`SAFE`, `SHORTAGE`, `NO CLASSES`).

3. **Attendance Shortage Calculator (Core Feature)**:
   - Applies the mathematical formula:
     $$\text{Minimum Additional Classes} = \left\lceil \frac{R \times C - A}{1 - R} \right\rceil$$
     *(where $R = \text{Required Fraction}$, $C = \text{Classes Conducted}$, $A = \text{Classes Attended}$)*
   - Edge case handling: Zero classes conducted initial state, 100% attendance impossibility proof when absences exist.
   - Verification step ensuring that attending the calculated classes strictly reaches or exceeds the threshold.
   - Projected attendance display showing before vs. after statistics.

4. **Safe Class Bunk / Buffer Calculator**:
   - For safe courses ($A/C \ge R$), calculates how many upcoming consecutive classes a student can miss without dropping below $R$:
     $$\text{Maximum Missable Classes} = \left\lfloor \frac{A}{R} - C \right\rfloor$$

5. **What-If Future Attendance Simulator**:
   - Enter hypothetical upcoming classes to attend or miss.
   - Real-time pure PHP projection of new conducted, attended, percentage, and compliance status.
   - Zero database modification during simulation.

6. **Subject-Wise Reports & Print View**:
   - Search & filter by subject and status (`All`, `Shortage Only`, `Safe Only`).
   - Print-friendly layout styled with `@media print` removing navigation, sidebars, and buttons for clean transcript printing (`Ctrl + P`).
   - Includes formal academic header, generation date/time, and signature blocks for Student, Faculty Advisor, and HoD.

7. **Administrator Control Panel**:
   - Campus-wide attendance shortage monitor showing all students at risk of disqualification.
   - Search directory by student name or register number.
   - Add, edit, remove students, and assign departments/semesters.
   - Catalog management: create, edit, delete subjects, and customize required percentages (default 75%).
   - Direct attendance override and auditing for any student.

---

## 🏗️ Technology Stack

| Layer | Technology | Description |
|---|---|---|
| **Frontend Structure** | HTML5 | Semantic structure, accessible forms, standard GET/POST |
| **Frontend Styling** | SCSS & CSS3 | Pure SCSS stylesheet compiled to `assets/css/style.css` (No JS / Tailwind / Bootstrap) |
| **Backend Engine** | PHP 8+ | Pure object-oriented & procedural PHP, PDO database abstraction |
| **Database** | MySQL 8.0+ / MariaDB | Normalized relational schema with foreign key cascades & indexes |

---

## 📁 Project Structure

```
AttendEase/
├── index.php                     # Welcome gateway and portal selector
├── register.php                  # Student registration with validation & auto-enrollment
├── login.php                     # Multi-role authentication (Email or Register No)
├── logout.php                    # Secure session destruction and redirect
├── dashboard.php                 # Student dashboard & academic KPI cards
├── subjects.php                  # Student course enrollment and viewing
├── attendance.php                # Student attendance logging and record updates
├── shortage_calculator.php       # Core shortage calculator & buffer bunk calculator
├── what_if_calculator.php        # Pure PHP hypothetical attendance simulator
├── reports.php                   # Academic statement with print layout
├── README.md                     # Complete project documentation and guide
├── admin/
│   ├── dashboard.php             # Admin overview & campus-wide shortage monitor
│   ├── students.php              # Student directory, CRUD, and search
│   ├── manage_subjects.php       # Subject catalog & requirement configuration
│   └── manage_attendance.php     # Direct attendance auditing and overrides
├── config/
│   └── database.php              # PDO singleton with automatic DB auto-bootstrapping
├── includes/
│   ├── auth.php                  # Session guards, role checking, password security
│   ├── functions.php             # Core calculation algorithms, CSRF & XSS helpers
│   ├── header.php                # Responsive layout header & CSS mobile drawer
│   └── footer.php                # Layout footer
├── assets/
│   ├── scss/
│   │   └── style.scss            # Master SCSS source with variables and components
│   └── css/
│       └── style.css             # Compiled browser stylesheet
└── database/
    └── attendance_system.sql     # Full MySQL schema and sample seed data
```

---

## 🚀 Setup & Execution Guide (Windows using XAMPP)

### Step 1: Install and Launch XAMPP
1. Download and install **XAMPP** (with PHP 8.0+) from [Apache Friends](https://www.apachefriends.org/).
2. Open the **XAMPP Control Panel**.
3. Click **Start** for both **Apache** and **MySQL**. Verify both show a green "Running" status.

### Step 2: Deploy AttendEase into `htdocs`
1. Navigate to your XAMPP installation directory (typically `C:\xampp\htdocs\`).
2. Move or copy this entire `AttendEase` folder into `htdocs`:
   ```
   C:\xampp\htdocs\AttendEase\
   ```

### Step 3: Database Setup via phpMyAdmin
AttendEase features an **automatic database bootstrapper** in `config/database.php` that will create and seed the database on your very first browser visit! However, if you prefer manual setup via phpMyAdmin:
1. Open your web browser and navigate to `http://localhost/phpmyadmin/`.
2. Click **New** in the left sidebar to create a database.
3. Name the database `attendance_system` with collation `utf8mb4_unicode_ci` and click **Create**.
4. Select the newly created `attendance_system` database.
5. Click the **Import** tab at the top.
6. Click **Choose File** and select `C:\xampp\htdocs\AttendEase\database\attendance_system.sql`.
7. Scroll down and click **Import**. You will see a success banner confirming all tables and sample data were created.

### Step 4: Access AttendEase
Open your browser and navigate to:
```
http://localhost/AttendEase/
```

---

## 🔑 Pre-Seeded Demo Credentials

| Role | Login Identifier (Email or Reg No) | Password | Description |
|---|---|---|---|
| **Administrator** | `admin@attendease.edu` or `ADMIN001` | `AdminPassword123!` | Full admin access (campus shortage monitor, subject management, student CRUD) |
| **Student (Alex Johnson)** | `student@attendease.edu` or `REG2026CS101` | `StudentPassword123!` | Semester 5 CSE student with mixed attendance (80% safe, 66.67% shortage, 100% safe, 0 conducted) |
| **Student (Priya Sharma)** | `priya@attendease.edu` or `REG2026CS102` | `StudentPassword123!` | Semester 5 CSE student with critical shortage in multiple subjects |

---

## 🧮 Mathematical Verification & Edge Case Test Cases

### Test Case 1: Standard Attendance Shortage (75% Threshold)
- **Input**: Classes Conducted $C = 36$, Classes Attended $A = 24$, Required $R = 0.75$ ($75\%$).
- **Current Attendance**: $(24 / 36) \times 100 = 66.67\%$ (Status: `SHORTAGE`).
- **Calculation**:
  $$\text{Classes Needed} = \left\lceil \frac{0.75 \times 36 - 24}{1 - 0.75} \right\rceil = \left\lceil \frac{27 - 24}{0.25} \right\rceil = \left\lceil \frac{3}{0.25} \right\rceil = 12$$
- **Verification**:
  - Projected Conducted = $36 + 12 = 48$
  - Projected Attended = $24 + 12 = 36$
  - Projected Percentage = $(36 / 48) \times 100 = 75.00\%$ ($\ge 75\%$, requirement satisfied!).

---

### Test Case 2: Attendance Buffer / Missable Classes
- **Input**: Classes Conducted $C = 40$, Classes Attended $A = 32$, Required $R = 0.75$ ($75\%$).
- **Current Attendance**: $(32 / 40) \times 100 = 80.00\%$ (Status: `SAFE`).
- **Calculation**:
  $$\text{Max Missable Classes} = \left\lfloor \frac{32}{0.75} - 40 \right\rfloor = \lfloor 42.6667 - 40 \rfloor = 2$$
- **Verification**:
  - If student misses 2 upcoming classes: Attended = 32, Conducted = $40 + 2 = 42$.
  - Percentage = $(32 / 42) \times 100 = 76.19\%$ ($\ge 75\%$, Safe!).
  - If student misses 3 classes: $(32 / 43) \times 100 = 74.42\%$ (Shortage!).
  - Exactly 2 classes can be missed.

---

### Test Case 3: Initial Course State (Zero Classes Conducted)
- **Input**: Classes Conducted $C = 0$, Classes Attended $A = 0$, Required $R = 0.75$.
- **Behavior**: Guard clause prevents division by zero ($C = 0$).
- **Output**: Percentage = $0.00\%$, Status = `NO CLASSES`.
- **Message**: *"No classes have been conducted yet. Attendance cannot yet be calculated until classes begin."*

---

### Test Case 4: 100% Attendance Requirement with Past Absence
- **Input**: Classes Conducted $C = 30$, Classes Attended $A = 28$, Required $R = 1.00$ ($100\%$).
- **Behavior**: Formula denominator $(1 - R) = 0$ is detected.
- **Output**: Flags `is_impossible_100 = true`.
- **Explanation**: *"The required attendance is 100%, but you have already missed 2 classes. Reaching 100% is mathematically impossible through future attendance alone."*

---

### Test Case 5: Exact Threshold Boundary
- **Input**: Classes Conducted $C = 40$, Classes Attended $A = 30$, Required $R = 0.75$ ($75\%$).
- **Current Attendance**: $(30 / 40) \times 100 = 75.00\%$.
- **Output**: Status = `SAFE`, Additional Classes Needed = $0$, Safe Bunk Classes = $0$.

---

## 🔒 Security Architecture

1. **Prepared Statements**: All database operations use PDO prepared statements with parameter binding, eliminating SQL injection.
2. **Cross-Site Scripting (XSS) Prevention**: All user-supplied and database-rendered values are escaped using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` through the global helper `e()`.
3. **Cross-Site Request Forgery (CSRF)**: Every POST form contains a hidden `csrf_token` validated against `$_SESSION['csrf_token']` via `hash_equals()`.
4. **Access Control & Route Guards**: Each protected page enforces `require_student()` or `require_admin()` to prevent unauthorized URL manipulation.
5. **Session Fixation Defense**: `session_regenerate_id(true)` is executed immediately upon authentication.
