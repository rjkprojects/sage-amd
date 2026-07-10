# S.A.G.E. — System for Academic Governance and Evaluation
### Rebuilt Exclusively for the AMD Developer Hackathon: Act II

S.A.G.E. is an AI-powered governance and academic audit platform that automates curriculum compliance, learning outcome mapping, and teaching material verification for higher education institutions.

S.A.G.E. was rebuilt exclusively for the **[AMD Developer Hackathon: Act II](https://lablab.ai/ai-hackathons/amd-developer-hackathon-act-ii)**, powered by **AMD Fireworks AI** server-side inference and the **Brave Search API** for agentic live-web auditing.

### 📚 System Documentation & Blueprints
Explore our comprehensive whitepaper, requirements specifications, and system architecture diagrams:
*   **[S.A.G.E. Academic Governance Whitepaper](whitepaper/index.html)** — Detailed study on LLMs for higher education compliance.
*   **[Product Requirements Document (PRD)](whitepaper/prd.html)** — Functional specs, user stories, and system constraints.
*   **[UML & Database Design Blueprints](whitepaper/uml.html)** — UML Use Cases, Sequence Flows, Database ERD, and Agent Logic flowcharts.

### 🎥 Screencast Demonstration
Watch S.A.G.E. in action, performing dynamic folder scanning, agent-based compliance audits, live web-verification via Brave Search, and role-based portal dashboards:

![S.A.G.E. Walkthrough Screencast](sage-screencast.mp4)

---

## 🚀 Key Features

*   **Role-Based Portals:** Custom dashboards for **Academic Lead**, **Coordinators**, and **Lecturers** to submit, monitor, and view audit reports.
*   **Autonomous Agent Loop:** Uses XML-based tool execution blocks to autonomously request baseline definitions, execute live-web searches, and write final reports to the database.
*   **Exclusive Fireworks AI Integration:** Rebuilt from the ground up to route all inference through Fireworks AI using their high-performance **GLM-5p2** model (`accounts/fireworks/models/glm-5p2`) hosted on AMD hardware.
*   **Live Web Auditing:** Integrated with **Brave Search API** so the agent can query the internet for the currency and recency of software, libraries, and frameworks mentioned in teaching materials.
*   **Server-Side Security:** Handles all API requests on the backend to safeguard API keys.

---

## 🛠️ Technology Stack

*   **Core Backend:** PHP 8.x
*   **Database:** MySQL (Relational storage for modules, audit results, logs, and users)
*   **CSS System:** Custom clean theme + space-modern fonts
*   **AI Infrastructure:** 
    *   **Inference:** AMD Fireworks AI API (`accounts/fireworks/models/glm-5p2`)
    *   **Context Window:** Up to 131,072 tokens (`max_tokens`) for complete course guides ingestion
    *   **Search Gateway:** Brave Search API (Web Audit)

### 💡 Why PHP & MySQL? (Infrastructure Creativity)
Initially, S.A.G.E. was conceived as a **no-budget project**. We did not have dedicated cloud resources to deploy resource-intensive Python/Flask/Django/FastAPI containers or Node.js microservices.

To make this solution viable, we had to be highly creative with our infrastructure. We decided to piggyback on the institution's existing shared website server, which only supported a PHP and MySQL stack. Operating within these strict constraints forced us to build S.A.G.E. entirely in native PHP, showing that robust AI agent systems, server-side inference routing (via AMD Fireworks AI), and real-time live-web auditing (via Brave Search API) can be built efficiently without expensive infrastructure overhead.

---

## 📁 Repository Structure

```text
├── dashboards/            # Role-based PHP dashboards (Academic Lead, Coordinator, Lecturer)
├── css/                   # Styled output and typography sheets
├── db/                    # DB connection definitions and setups
├── _whitepaper/           # Beautiful visual concept documentation (index.html)
├── .env                   # Local credentials for MySQL, Fireworks, and Brave APIs
├── agent.php              # Coordinates coordinator document audit step sequences
├── chat.php / nexus.php   # Handles real-time interactive user questions
├── handshake.php          # Security gate, environment loader, and prompt storage
├── index.php              # Login panel and custom portal routing
├── tools.php              # Definition of tools available to S.A.G.E. (e.g. Brave Search)
└── README.md              # Project documentation
```

---

## ⚙️ Setup and Configuration

1. **Environment Variables (`.env`)**
   Create a `.env` file in the root directory:
   ```ini
   _DB_HOST=[your_mysql_host]
   _DB_NAME=sage_amd
   _DB_USER=[your_mysql_db]
   _DB_PASS=[your_mysql_password]
   _FIREWORKS_API_URL=https://api.fireworks.ai/inference/v1/chat/completions
   _FIREWORKS_API_KEY=[your_fireworks_api_key]
   _FIREWORKS_API_MODEL=accounts/fireworks/models/glm-5p2
   _CHAT_CONTEXT_WINDOW=10
   _BRAVE_API_KEY=[your_brave_api_key]
   ```

2. **Database Setup**
   Import the schema into your MySQL database using:
   ```bash
   mysql -u root -p sage_amd < sage_amd.sql
   ```

3. **Running the Application**
   Host S.A.G.E. using any PHP 8 environment (such as Laragon, XAMPP, or PHP's built-in web server):
   ```bash
   php -S localhost:8000
   ```

---

## 📝 License & Hackathon Info
This project is a submission for the **AMD Developer Hackathon: Act II**. Built for academic governance, quality assurance, and automated curriculum validation.

---

## 👥 Walkthrough & User Portals

S.A.G.E. features three distinct user portals based on academic roles. Below is a walkthrough of how each role interacts with the platform.

### 🔑 Demo Login Credentials
For testing and demonstration, use the following credentials (defined in `db/sage_amd.sql`):

Hackathon Demo: https://elo.xo.je/sage/

| Role | Username | Password | Purpose |
| :--- | :--- | :--- | :--- |
| **Academic Lead** | `lead` | `123` | Curriculum-wide oversight and interactive S.A.G.E. Chat |
| **Coordinator** | `coord` | `123` | Folder upload ingestion and initiating document audits |
| **Lecturer** | `christo` | `123` | Direct access to audited course reports for their modules |

---

### 1. 🎓 Academic Lead Portal
The Academic Lead is responsible for the overall monitoring of curriculum quality and has access to **S.A.G.E. Chat** to interrogate the academic database.

*   **Audit Monitoring:** Leads can view all reports, modules, and scores across all faculties.
*   **Interactive S.A.G.E. Chat:** Leads can ask complex questions about the modules, compliance, or industry outcomes. S.A.G.E. automatically queries relational tables (like `modules`, `module_learning_outcomes`, `module_assessments`, etc.) and uses web-search to answer.
*   **Example Chat Questions (from `db/sage_amd.sql`):**
    *   *"In Faculty of AI, what is Introduction to Image Processing module is all about?"*
    *   *"Whats Deep Neural Network Architectures in Faculty AI is all about?"*
    *   *"So how many assessments in that module?"*
    *   *"Looking at our Faculty AI summary, in nowadays industry what will the student can expect to be after graduating"*
    *   *"Back to module, what is week 8 in Deep Neural Network Architectures class is all about?"*

### 2. 📂 Coordinator Portal (Folder Ingestion & Auditing)
Coordinators manage the uploading of teaching materials to the repository and trigger audits.

*   **No Upload Forms / UI Complexities:** Instead of manually uploading files one-by-one through a form, coordinators simply drop/upload folders directly into the project's root `repository/` directory.
*   **Folder Structure Convention:** S.A.G.E. dynamically scans the repository directory using recursive traversal. The directory structure must follow this path convention:
    ```text
    repository/[term_name]/[faculty_department]/[lecturer_username]/[module_code]/[material_type]/[filename]
    ```
    *   `material_type` can be: `lesson_plan`, `assignment`, or `lecture_material`.
    *   *Example Path:*
        `repository/term_1_2026/ai/christo/TBAI22013a/lesson_plan/01_introduction.pdf`
*   **One-Click Audit:** Once folders are copied into the repository, S.A.G.E. automatically lists them on the Coordinator dashboard as `Untracked`. The coordinator simply clicks the **Audit** button. S.A.G.E. will:
    1. Retrieve the module's baseline learning outcomes (MLOs) from the database.
    2. Read the uploaded document.
    3. Perform live-web searches (via Brave Search API) to verify if the mentioned frameworks/technologies are current.
    4. Validate compliance, language appropriateness, and generate an audit report and score (stored back in the database).
*   **Review:** The coordinator clicks **View Report** to read S.A.G.E.'s constructive feedback, strengths, weaknesses, and modern suggestions.

### 3. 👨‍🏫 Lecturer Portal
Lecturers have a streamlined portal to access feedback on their materials directly.

*   **Automatic Dashboard Mapping:** S.A.G.E. maps the audited documents to the lecturer based on their username matching the folder name (e.g., `christo` in the `repository/.../christo/...` folder path).
*   **Dashboard Review:** When a lecturer logs in (e.g., `christo`), any checked documents in their folders appear immediately with their compliance status (`OK` or `Need Review`). The lecturer simply clicks to read the feedback and actionable recommendations.
