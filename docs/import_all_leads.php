<?php

require __DIR__ . '/../vendor/autoload.php';

$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'laravel_crm';
$_ENV['DB_USERNAME'] = 'crm_user';
$_ENV['DB_PASSWORD'] = 'SecurePass123!';

putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_DATABASE=laravel_crm');
putenv('DB_USERNAME=crm_user');
putenv('DB_PASSWORD=SecurePass123!');

$app = require __DIR__ . '/../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$app->register(\VentureDrake\LaravelCrm\LaravelCrmServiceProvider::class);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

config(['laravel-crm.db_table_prefix' => 'crm_']);

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Address;
use VentureDrake\LaravelCrm\Models\AddressType;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\Label;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Note;

$userModel = config('auth.providers.users.model') ?? \App\Models\User::class;
$owner = $userModel::first();
$userId = $owner ? $owner->id : 1;

// Get or create Lead Source
$leadSource = LeadSource::firstOrCreate(['name' => 'LinkedIn Sourced Leads'], [
    'external_id' => Str::uuid()->toString(),
]);

// Get or create Label
$labelQuant = Label::firstOrCreate(['name' => 'LinkedIn Lead'], [
    'external_id' => Str::uuid()->toString(),
    'hex' => '#3B82F6',
]);

// Get Lead Pipeline & Stage
$leadPipeline = Pipeline::where('model', Lead::class)->first() ?? Pipeline::first();
$leadStages = PipelineStage::where('pipeline_id', $leadPipeline->id)->get();
$leadStageNew = $leadStages->firstWhere('name', 'New') ?? $leadStages->first();
$leadStageQual = $leadStages->firstWhere('name', 'Qualified To Buy') ?? $leadStages->skip(1)->first();

$addressType = AddressType::where('name', 'Primary')->first() ?? AddressType::first();

$maxLeadNum = Lead::max('number') ?? 1000;
$leadIdx = $maxLeadNum + 1;

$leadsPayload = [
    {
        "first_name": "Alex",
        "last_name": "Lokhov",
        "headline": "Data Strategy and Sourcing @ Jump Trading.",
        "org_name": "Jump Trading Group",
        "location": "London, England, United Kingdom",
        "linkedin": "https://www.linkedin.com/in/alex-lokhov-7608161",
        "amount": 12500.0,
        "title": "Alex Lokhov - Jump Trading Group - Data Strategy and Sourcing",
        "summary": "Data Strategy and Sourcing at Jump Trading Group. Located in London, England, United Kingdom.",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/alex-lokhov-7608161\\nHeadline: Data Strategy and Sourcing @ Jump Trading.\\nConnections: 2783 | Followers: 2792\\nLocation: London, England, United Kingdom\\n\\nExperience:\\n- Data Strategy and Sourcing @ Jump Trading Group (Mar 2025 - Present)\\n- Head of Research @ Hatched Analytics (Sep 2022 - May 2024)\\n- Founding Partner @ Adversus Capital Ltd (Aug 2018 - Sep 2022)\\n- Principal Sales Engineer @ Tableau Software (Jan 2016 - Aug 2020)\\n- Senior SE - Major Finance District @ EMC (Jul 2012 - Dec 2015)\\n- Technical Account Manager @ MTI Technology (Jan 2011 - Jul 2012)\\n\\nEducation:\\n- The University of Edinburgh (MSc - Computer Science)\\n\\nCertifications:\\n- dbt Certified Analytics Engineer (dbt Labs)\\n\\nTop Skills:\\nAnalytics, Consultative Selling, Alternative Data, Cloud Computing, Virtualization, Storage, Sales, Data Center, Pre-sales, Solution Selling, Business Intelligence, Machine Learning, Data Analysis, Fixed Income, VMware"
    },
    {
        "first_name": "Muyun",
        "last_name": "Chu",
        "headline": "EssilorLuxotticaEMEA Logistics Sourcing Data analyst",
        "org_name": "EssilorLuxottica",
        "location": "Paris, Île-de-France, France",
        "linkedin": "https://www.linkedin.com/in/muyun-chu233",
        "amount": 3500.0,
        "title": "Muyun Chu - EssilorLuxottica - EMEA Logistics Sourcing Data analyst",
        "summary": "Since I majored in finance during my bachelor years, I had some experiences working in finance, which enhanced my working and communication skills. Now, I'm heading for the position as data scientist and data analyst after my study in data science fi",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/muyun-chu233\\nHeadline: EssilorLuxotticaEMEA Logistics Sourcing Data analyst\\nConnections: 550 | Followers: 550\\nLocation: Paris, Île-de-France, France\\n\\nAbout:\\nSince I majored in finance during my bachelor years, I had some experiences working in finance, which enhanced my working and communication skills. Now, I'm heading for the position as data scientist and data analyst after my study in data science field. I believe with my passion into data analysis and data science, my expertise will get well trained under the pracitical situtation!\\n\\nExperience:\\n- EMEA Logistics Sourcing Data analyst @ EssilorLuxottica (Mar 2026 - Present)\\n- Global Client Data & Insight Intern @ BALENCIAGA (Jan 2025 - Jun 2025)\\n- Intern @ Caitong Securities Ltd (Sep 2022 - Oct 2022)\\n- Assistant Analyst @ Baker Tilly China (Mar 2022 - May 2022)\\n\\nEducation:\\n- emlyon business school\\n- McGill University (Computer Science - )\\n\\nCertifications:\\n- AWS Certified Machine Learning - Specialty (Amazon Web Services (AWS))\\n- AWS Certified Cloud Practitioner 认证 (Amazon Web Services (AWS))\\n\\nTop Skills:\\nStatistical Data Analysis, Data Management, Market Research, Wealth Management Services, Pycharm, NumPy, 统计数据分析, 数据管理, 市场研究, 潜在客户开发, 财富管理服务, 物联网, 项目管理, 深度学习, 自然语言处理"
    },
    {
        "first_name": "William Data",
        "last_name": "Denholm　ウィリアム デンホルム",
        "headline": "Radiant Japan / SHJ Founder - THE Award winning Data Science, AI, Robotics recruitment company  radiantjapan.com   - Japan Market Entry Specialist",
        "org_name": "Radiant Japan",
        "location": "Tokyo, Tokyo, Japan",
        "linkedin": "https://www.linkedin.com/in/william-data-denholm　ウィリアム-デンホルム-918900106",
        "amount": 12500.0,
        "title": "William Data Denholm　ウィリアム デンホルム - Radiant Japan - Radiant Japan/ SHJ - Founder  - THE Data company",
        "summary": "Over 2 decades of proven global recruitment experience opening doors and consistently exceeding sales targets.  An expert in building relationships (people, stakeholder and account management).  Works well as part of a team or autonomously.  Entrepre",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/william-data-denholm　ウィリアム-デンホルム-918900106\\nHeadline: Radiant Japan / SHJ Founder - THE Award winning Data Science, AI, Robotics recruitment company  radiantjapan.com   - Japan Market Entry Specialist\\nConnections: 22022 | Followers: 21503\\nLocation: Tokyo, Tokyo, Japan\\n\\nAbout:\\nOver 2 decades of proven global recruitment experience opening doors and consistently exceeding sales targets.  An expert in building relationships (people, stakeholder and account management).  Works well as part of a team or autonomously.  Entrepreneurial, ambitious, tenacious and driven.  www.radiantjapan.com\\n\\nExperience:\\n- Radiant Japan/ SHJ - Founder  - THE Data company @ Radiant Japan (Jan 2016 - Present)\\n- Business Development Head  @ Globesoft Services Pte. Ltd. (EA License no. 12C6296) (Nov 2014 - Oct 2015)\\n- Team Leader / 360 Billing consultant- Drilling Midas @ Empiric (Feb 2013 - Oct 2014)\\n- Head of Contract Recruitment @ Argyll Scott Hong Kong (formerly REED Hong Kong) (Oct 2010 - Dec 2012)\\n- MD and Business Owner / Founder @ Transparent Consultancy (2006 - 2010)\\n- IT Contract Recruitment Consultant / Team Lead @ Computer Futures (1999 - 2006)\\n\\nEducation:\\n- University of the West of England (Business & Finance - )\\n- St Albans School\\n\\nCertifications:\\n- Licenced Singapore recruiter (Ministry of Manpower)\\n- Japanese Registered responsible recruitment company representative  (001-171212131-08347) ()\\n- PADI DIVE MASTER 261311 (PADI)\\n\\nTop Skills:\\nIT Recruitment, Recruiting, Business Development, Consulting, Contract Recruitment, Talent Acquisition, Permanent Placement, Screening Resumes, Executive Search, Technical Recruiting, Sourcing, Temporary Staffing, Recruitment Advertising, Temporary Placement, Data Scientist"
    },
    {
        "first_name": "Jeanne",
        "last_name": "Carey, LSSGB, LCS 1C, PMP, CSM",
        "headline": "Enterprise Process & Transformation Leader | SAP ERP • PMP • Prosci • Lean Six Sigma | 25+ Years, Fortune 100 to Growth-Stage",
        "org_name": "Keystone Thinking",
        "location": "Marshfield, Massachusetts, United States",
        "linkedin": "https://www.linkedin.com/in/jeannegcarey",
        "amount": 6500.0,
        "title": "Jeanne Carey, LSSGB, LCS 1C, PMP, CSM - Keystone Thinking - Founder/Principal Consultant",
        "summary": "I build the systems that hold an enterprise together once the reorg, the merger, or the transformation is \"done.\"\n \nFor 25+ years, I've been the person organizations call in when processes are scattered, governance doesn't exist yet, and nobody can a",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/jeannegcarey\\nHeadline: Enterprise Process & Transformation Leader | SAP ERP • PMP • Prosci • Lean Six Sigma | 25+ Years, Fortune 100 to Growth-Stage\\nConnections: 1157 | Followers: 1269\\nLocation: Marshfield, Massachusetts, United States\\n\\nAbout:\\nI build the systems that hold an enterprise together once the reorg, the merger, or the transformation is \"done.\"\n \nFor 25+ years, I've been the person organizations call in when processes are scattered, governance doesn't exist yet, and nobody can agree on how work actually flows across the business. My job is to design that structure — the taxonomy, the governance model, the KPI framework — and then get diverse stakeholders, from frontline teams to the C-suite, aligned around it.\n \nA few things I've built along the way:\n \n* Directed a global SAP deployment across 162,000 users, stabilized within 45 days of go-live\n* Co-led the integration of A.T. Kearney's consulting practice into EDS's IT and business architecture — harmonizing two very different operating models into one\n* Designed an enterprise-wide process framework projected to save $3B annually, later standardized into SAP and MS Project Server as core deliverables\n* Built the process taxonomy and governance structure behind a GM board-approved, $411B document management initiative\n* Created the Process Mastery methodology, later adopted enterprise-wide across EDS\n \nI've done this work in automotive, healthcare, energy, and food manufacturing — different industries, same underlying pattern: process chaos becomes a governed system that outlives my involvement.\n \nCertified PMP, Prosci Change Practitioner, Lean Six Sigma Green Belt, and Certified Scrum Master. Fluent in Lean, TPS, Value Stream Mapping, Shingo, and Design Thinking, with deep hands-on process modeling in Microsoft Visio.\n \nCurrently working with organizations through Keystone Thinking, where I help leadership teams define future-state operating models and turn strategic vision into governed, scalable process architecture.\n \nIf your organization has outgrown its own processes — or never had governance structures to begin with — that's the conversation I'd like to have.\n\n“Our willingness to acknowledge that we only see half the picture creates the conditions that make us more attractive to others. The more sincerely we acknowledge our need for their different insights and perspectives, the more they will be magnetized to join us.” Margaret J. Wheatley\\n\\nExperience:\\n- Founder/Principal Consultant @ Keystone Thinking (Jun 2024 - Present)\\n- Home Energy Rater (HERS Rater) @ Home Energy Raters (Jul 2024 - May 2025)\\n- Quality Manager  @ Gainwell Technologies (Mar 2021 - Jul 2022)\\n- Caregiving @ Career Break (Oct 2019 - Mar 2021)\\n- Program Manager @ Brown University Health (Oct 2018 - Oct 2019)\\n- Principal Process Manager @ National Grid (Jan 2018 - Sep 2018)\\n\\nEducation:\\n- State University of New York at Oswego (Bachelor of Science - BS - Biology/Biological Sciences, and Secondary Education)\\n- Lansing Community College (Associate of Arts and Sciences (A.A.S.) - Alternative Energy Engineering Technology)\\n\\nCertifications:\\n- Prosci® Certified Change Practitioner (Prosci)\\n- Project Management Professional (PMP)® (Project Management Institute)\\n- Building a Broad Network of Influence (LinkedIn)\\n- LCS 1C - equal to black/green belt (Cardiff University / Prifysgol Caerdydd)\\n- Certified Scrum Master (William George Associates)\\n- Lean Six Sigma Green Belt (William George Associates)\\n\\nTop Skills:\\nEnterprise Process Improvement, SAP Implementation, Enterprise Consulting, Organizational Consulting, Small Business Consulting, Facilitation, Toyota Production System, Lean Six Sigma, Agile Methodologies, Management of Change (MOC), IT Project & Program Management, Organizational Change Management, Large Scale Change Management, Program Management, Corporate Governance"
    },
    {
        "first_name": "Gareth",
        "last_name": "Jones",
        "headline": "Senior Software Engineer",
        "org_name": "Minute Cryptic",
        "location": "Australia",
        "linkedin": "https://www.linkedin.com/in/garjon",
        "amount": 6500.0,
        "title": "Gareth Jones - Minute Cryptic - Senior Software Engineer",
        "summary": "Howdy 👋 I consider myself a full stack software engineer who loves picking up diverse technologies and making them work wonders. My current sweet spot lies in using Typescript with Next.js and React, but I'm always eager to expand my tech toolkit.\n\nA",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/garjon\\nHeadline: Senior Software Engineer\\nConnections: 674 | Followers: 715\\nLocation: Australia\\n\\nAbout:\\nHowdy 👋 I consider myself a full stack software engineer who loves picking up diverse technologies and making them work wonders. My current sweet spot lies in using Typescript with Next.js and React, but I'm always eager to expand my tech toolkit.\n\nAt the heart of my work philosophy is a love for being part of small, dynamic teams in early-stage startups. I thrive in environments where I can contribute beyond the typical 'developer' role - engaging with all levels of the business to collaboratively build something truly remarkable.\n\nI also dabble in levelling up the teams I work with. Whether it's refining processes or nurturing the growth of individuals and the team as a whole, I love making a positive change that brings out the best in everyone involved.\n\nCurrently, I'm embracing a phase in my life where working remotely aligns best with my personal and professional goals. This flexibility allows me to deliver high-quality work while maintaining a balanced lifestyle.\n\nI'm excited to connect with like-minded professionals and organisations that value creativity, collaboration, and a holistic approach to technology and business. Let's explore how we can create something incredible together!\\n\\nExperience:\\n- Senior Software Engineer @ Minute Cryptic (Jun 2025 - Present)\\n- Lead Developer & Founder @ Calmly (Aug 2024 - Jun 2025)\\n- Staff Software Engineer @ Amber Electric (Dec 2023 - Jul 2024)\\n- Principal Software Engineer @ Powerpal (Apr 2021 - Dec 2023)\\n- Software Engineering Manager @ Blueshift (Sep 2018 - Apr 2021)\\n- Senior Software Engineer @ Blueshift (Aug 2017 - Sep 2018)\\n\\nEducation:\\n- University of Newcastle (Bachelor of Computer Science - )\\n\\nTop Skills:\\nPostgres, Product Strategy, Full-Stack Development, User-centered Design, Agile & Iterative Development, HTML5, OpenAI API, fly.io, Vercel, Next.js, Supabase, Node.js, Product Development, React Native, Mentoring"
    },
    {
        "first_name": "Carlos Andrés",
        "last_name": "Cabrera Tovar",
        "headline": "Senior Researcher @MichaelPage / Psychologist / Talent Acquisition / Culture & Change Management",
        "org_name": "Michael Page",
        "location": "Monterrey, Nuevo León, Mexico",
        "linkedin": "https://www.linkedin.com/in/imcact",
        "amount": 6500.0,
        "title": "Carlos Andrés Cabrera Tovar - Michael Page - Senior Researcher",
        "summary": "Founded in England in 1976, Michael Page is a worldwide reference in specialized recruitment of professionals for middle to senior management, for permanent and temporary positions. It is part of the PageGroup, renowned for the extensive experience o",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/imcact\\nHeadline: Senior Researcher @MichaelPage / Psychologist / Talent Acquisition / Culture & Change Management\\nConnections: 5799 | Followers: 6012\\nLocation: Monterrey, Nuevo León, Mexico\\n\\nAbout:\\nFounded in England in 1976, Michael Page is a worldwide reference in specialized recruitment of professionals for middle to senior management, for permanent and temporary positions. It is part of the PageGroup, renowned for the extensive experience of its consultants and the quality of its services. Listed on the London Stock Exchange, has a global structure that ensures high-level results.\n\nPageGroup is focused on finding professionals for 12 different divisions:\n\nConstruction\nBanking & Financial Services \nEngineering & Manufacturing \nFinance & Tax \nHealthcare \nHuman Resources \nInformation Technology \nInsurance \nLegal \nProperty & Construction \nRetail \nSales & Marketing \nSupply Chain & Procurement\\n\\nExperience:\\n- Senior Researcher @ Michael Page (Mar 2025 - Present)\\n- Chief of Staff / HR Manager @ Timeless Brands (Dec 2023 - Mar 2025)\\n- Head of Talent Acquisition @ Cápita Works - Bilingual Virtual Assistants in Mexico (Dec 2021 - Feb 2024)\\n- Talent Acquisition Coordinator @ Cápita Works - Bilingual Virtual Assistants in Mexico (Jun 2021 - Dec 2021)\\n- Talent Acquisition Specialist @ Cápita Works - Bilingual Virtual Assistants in Mexico (Feb 2021 - Jun 2021)\\n- Executive Recruiter and Evaluation Specialist @ Banesco Banco Universal (Oct 2018 - Feb 2021)\\n\\nEducation:\\n- UCAB - Universidad Católica Andrés Bello (Bachelor's degree - Psychology)\\n- Universidad Central de Venezuela (Master of Science - MS - Cultural Policies and Management)\\n- UCAB - Universidad Católica Andrés Bello (Diploma - Organizational Change Management)\\n- Universidad del Salvador (Diploma - Korean Studies)\\n\\nCertifications:\\n- An Introduction to Japanese Subculture (FutureLearn)\\n- Teoría y práctica ante situaciones traumáticas, intervención en crisis para victimas en situaciones adversas, abordaje del duelo y acompañamiento al que sufre. (UCAB - Universidad Católica Andrés Bello)\\n- Assesment Center: una herramienta para la evaluación de competencias (UCAB - Universidad Católica Andrés Bello)\\n- El rol del psicólogo en el cambio de las instituciones (UCAB - Universidad Católica Andrés Bello)\\n\\nTop Skills:\\nFacilidad de adaptación, Project Management, Human Resources (HR), Conflict Management, Change Management, Negotiation, Customer Satisfaction, Recruiting, Team Leadership, Client Relations, Entrevistas por competencias, Evaluaciones psicológicas, Gestión del talento humano, Selección de personal, Análisis de datos estadísticos"
    },
    {
        "first_name": "Dung",
        "last_name": "Pham Nguyen Thuy",
        "headline": "Purchasing (mainly Sourcing) Executive tại Sedo Vina",
        "org_name": "Sedo Vina",
        "location": "Ho Chi Minh City, Vietnam",
        "linkedin": "https://www.linkedin.com/in/dung-pham-nguyen-thuy-797185285",
        "amount": 6500.0,
        "title": "Dung Pham Nguyen Thuy - Sedo Vina - Purchasing (mainly Sourcing) Executive",
        "summary": "I am pursuing a Bachelor of Talents (ISB BBus) program at the University of Economics HCMC. With a proactive, diligent, and adaptable mindset, I thrive on challenges and consistently seek opportunities to develop and grow.",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/dung-pham-nguyen-thuy-797185285\\nHeadline: Purchasing (mainly Sourcing) Executive tại Sedo Vina\\nConnections: 450 | Followers: 450\\nLocation: Ho Chi Minh City, Vietnam\\n\\nAbout:\\nI am pursuing a Bachelor of Talents (ISB BBus) program at the University of Economics HCMC. With a proactive, diligent, and adaptable mindset, I thrive on challenges and consistently seek opportunities to develop and grow.\\n\\nExperience:\\n- Purchasing (mainly Sourcing) Executive @ Sedo Vina (May 2026 - Present)\\n- Merchandise Intern @ Valanno Group (Jun 2025 - Nov 2025)\\n\\nEducation:\\n- UEH - International School of Business (Bachelor's degree - International Business)\\n- Nguyen Thuong Hien High School For The Gifted, HCMC, Vietnam\\n- University of Economics HCMC (Bachelor's Degree - International Business)\\n\\nCertifications:\\n- Get Started with Google AdMob (Google Digital Academy (Skillshop))\\n- Search Ads 360 Certification Exam (Google Digital Academy (Skillshop))\\n- Conversion Optimization Certification Exam (Google Digital Academy (Skillshop))\\n- Campaign manager 360 Certification Exam (Google Digital Academy (Skillshop))\\n- Google Ads Display Certification (Google Digital Academy (Skillshop))\\n- Grow Offline Sales Certification (Google Digital Academy (Skillshop))\\n\\nTop Skills:\\nMicrosoft Office, Monitoring Progress, Order Processing, Purchase Contracts, Leadership, Problem Solving, Procurement, Project Planning, Project Management, Data Analysis, Digital Marketing, Online Sales Management, Omni-Channel Marketing, Inventory Control, Shopping Ads"
    },
    {
        "first_name": "Tom",
        "last_name": "Kierman",
        "headline": "Qualified, Senior & Corporate Finance Search | Digital Infrastructure",
        "org_name": "KennedyPearce Consulting",
        "location": "London Area, United Kingdom",
        "linkedin": "https://www.linkedin.com/in/tom-kierman",
        "amount": 6500.0,
        "title": "Tom Kierman - KennedyPearce Consulting - Executive Consultant",
        "summary": "I specialise in Qualified, Senior Finance and Corporate Finance Appointments for PE-Backed, Infrastructure-Backed and High-Growth Digital Infrastructure Businesses.\n\nI partner with businesses that own, operate, develop, invest in and enable the criti",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/tom-kierman\\nHeadline: Qualified, Senior & Corporate Finance Search | Digital Infrastructure\\nConnections: 7268 | Followers: 10389\\nLocation: London Area, United Kingdom\\n\\nAbout:\\nI specialise in Qualified, Senior Finance and Corporate Finance Appointments for PE-Backed, Infrastructure-Backed and High-Growth Digital Infrastructure Businesses.\n\nI partner with businesses that own, operate, develop, invest in and enable the critical infrastructure powering the digital economy, helping them attract exceptional talent from newly qualified level through to CFO and senior Corporate Finance leadership.\n \n𝐅𝐢𝐧𝐚𝐧𝐜𝐞 𝐂𝐨𝐯𝐞𝐫𝐚𝐠𝐞\n𝘐 𝘵𝘺𝘱𝘪𝘤𝘢𝘭𝘭𝘺 𝘳𝘦𝘤𝘳𝘶𝘪𝘵 𝘧𝘰𝘳, 𝘣𝘶𝘵 𝘯𝘰𝘵 𝘭𝘪𝘮𝘪𝘵𝘦𝘥 𝘵𝘰, 𝘵𝘩𝘦 𝘧𝘰𝘭𝘭𝘰𝘸𝘪𝘯𝘨:\n▪ Finance Manager\n▪ Financial Controller\n▪ Group Financial Controller\n▪ Head of Finance\n▪ FP&A Manager / Head of FP&A\n▪ Commercial Finance Leadership\n▪ VP Finance\n▪ Finance Director\n▪ CFO\n▪ Corporate Development Manager / Director\n▪ Investment Manager / Director\n▪ M&A and Corporate Finance\n▪ Strategic Finance\n▪ Development Finance\n \n𝐂𝐨𝐫𝐞 𝐃𝐢𝐠𝐢𝐭𝐚𝐥 𝐈𝐧𝐟𝐫𝐚𝐬𝐭𝐫𝐮𝐜𝐭𝐮𝐫𝐞 𝐂𝐨𝐯𝐞𝐫𝐚𝐠𝐞\n𝘐 𝘸𝘰𝘳𝘬 𝘸𝘪𝘵𝘩 𝘣𝘶𝘴𝘪𝘯𝘦𝘴𝘴𝘦𝘴 𝘰𝘱𝘦𝘳𝘢𝘵𝘪𝘯𝘨 𝘢𝘤𝘳𝘰𝘴𝘴:\n▪ Data Centres\n▪ Fibre Networks\n▪ Connectivity Infrastructure\n▪ Mobile Infrastructure\n▪ Tower Infrastructure\n▪ Cloud Infrastructure\n▪ Managed Infrastructure\n▪ Managed Service Providers\n▪ Network Infrastructure\n▪ Satellite Connectivity\n \n𝐀𝐝𝐣𝐚𝐜𝐞𝐧𝐭 𝐌𝐚𝐫𝐤𝐞𝐭 𝐂𝐨𝐯𝐞𝐫𝐚𝐠𝐞\n𝘐 𝘢𝘭𝘴𝘰 𝘱𝘢𝘳𝘵𝘯𝘦𝘳 𝘸𝘪𝘵𝘩 𝘣𝘶𝘴𝘪𝘯𝘦𝘴𝘴𝘦𝘴 𝘴𝘶𝘱𝘱𝘰𝘳𝘵𝘪𝘯𝘨 𝘵𝘩𝘦 𝘸𝘪𝘥𝘦𝘳 𝘥𝘪𝘨𝘪𝘵𝘢𝘭 𝘪𝘯𝘧𝘳𝘢𝘴𝘵𝘳𝘶𝘤𝘵𝘶𝘳𝘦 𝘦𝘤𝘰𝘴𝘺𝘴𝘵𝘦𝘮, 𝘪𝘯𝘤𝘭𝘶𝘥𝘪𝘯𝘨:\n▪ AI Infrastructure\n▪ Digital Infrastructure Technology\n▪ Infrastructure Hardware Platforms\n▪ Storage Infrastructure\n▪ High-Performance Computing (HPC)\n▪ Critical Infrastructure Services\n \n𝐓𝐲𝐩𝐢𝐜𝐚𝐥 𝐂𝐥𝐢𝐞𝐧𝐭𝐬\n𝘔𝘺 𝘤𝘭𝘪𝘦𝘯𝘵𝘴 𝘢𝘳𝘦 𝘵𝘺𝘱𝘪𝘤𝘢𝘭𝘭𝘺:\n▪ Private Equity-backed Businesses\n▪ Infrastructure Fund-backed Businesses\n▪ Founder-led Scale-ups\n▪ Buy-and-Build Platforms\n▪ High-Growth Digital Infrastructure Businesses\n \nWhether supporting a first finance hire, building or strengthening a finance function, recruiting into a Corporate Development or Investments team, supporting buy-and-build growth, preparing for new investment or positioning a business for an exit, I help digital infrastructure organisations secure the talent required to achieve their ambitions.\n\nIf you're hiring Finance or Corporate Finance talent within the digital infrastructure sector, or you're a finance, investment or corporate development professional exploring opportunities within PE-backed, infrastructure-backed or high-growth businesses, please feel free to get in touch.\\n\\nExperience:\\n- Executive Consultant @ KennedyPearce Consulting (Oct 2024 - Present)\\n- Principal Consultant @ Hays (Nov 2023 - Oct 2024)\\n- Principal Consultant @ Hays (May 2023 - Nov 2023)\\n- Senior Recruitment Consultant @ Hays (Aug 2021 - May 2023)\\n\\nTop Skills:\\nNegotiation, Relationship Building, Sales, Executive Search, Project Management, Consulting, Team Building, Customer Service, Recruiting, Recruitment Advertising, Sourcing, Interviewing, Recruitment Marketing, Resume Writing"
    },
    {
        "first_name": "Muhammad Shariq Iqbal,",
        "last_name": "Microsoft Certified Data Analyst, MS(CS)",
        "headline": "Specialist, Data Management @ AD Ports Group | Computer Software Engineering",
        "org_name": "AD Ports Group",
        "location": "Abu Dhabi Emirate, United Arab Emirates",
        "linkedin": "https://www.linkedin.com/in/muhammad-shariq-iqbal-microsoft-certified-data-analyst-ms-cs-b0774b18",
        "amount": 6500.0,
        "title": "Muhammad Shariq Iqbal, Microsoft Certified Data Analyst, MS(CS) - AD Ports Group - Specialist, Data Management",
        "summary": "As an Specialist, Data Management at AD Ports Group, I manage and develop the Master Database, design and develop dashboards for management level decision-making, and conduct data cleansing and analysis to support the business development and campaig",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/muhammad-shariq-iqbal-microsoft-certified-data-analyst-ms-cs-b0774b18\\nHeadline: Specialist, Data Management @ AD Ports Group | Computer Software Engineering\\nConnections: 3417 | Followers: 3641\\nLocation: Abu Dhabi Emirate, United Arab Emirates\\n\\nAbout:\\nAs an Specialist, Data Management at AD Ports Group, I manage and develop the Master Database, design and develop dashboards for management level decision-making, and conduct data cleansing and analysis to support the business development and campaigns. I have more than 12 years of industry experience in collecting, transforming, and organizing data for analysis to help make informed decisions.\n\nI have an excellent understanding and proficiency of platforms for effective data analyses, including SQL, Excel Spreadsheets, Tableau, Power BI, and R. I also have strong communication, organizational, and analytical skills and the ability to circulate information in a way that is clear, efficient, and beneficial for end-users. My mission is to leverage data science to provide insights and solutions that optimize results and enable data-driven decision-making for the company and the clients.\\n\\nExperience:\\n- Specialist, Data Management @ AD Ports Group (Jan 2026 - Present)\\n- Analyst, Data Science @ AD Ports Group (Nov 2021 - Dec 2025)\\n- HRIS, Analytics @ Pure Home Real Estate (May 2021 - Sep 2021)\\n- Technical Officer / Business Analyst @ ADNOC Group (Nov 2011 - Dec 2020)\\n- Business Development Executive @ SBT Japan - Pakistan Office (Jan 2007 - Oct 2011)\\n\\nEducation:\\n- Shaheed Zulfikar Ali Bhutto Institute of Science and Technology (Master's degree - Computer Software Engineering)\\n- ILMA University - Formerly IBT (BS(Computer Science) - Computer Science)\\n\\nCertifications:\\n- Microsoft Ceritifed: AI Business Profesional (Microsoft)\\n- Digital Transformation Specialist Certifications (AD Ports Group)\\n- Microsoft Certified: Power BI Data Analyst Associate (Microsoft)\\n- Azure Management: Portal, PowerShell, and CLI Basics (LinkedIn)\\n- Lean Six Sigma Foundations (LinkedIn)\\n- Azure Machine Learning Development: Part 1 (LinkedIn)\\n\\nTop Skills:\\nCopilot Cowork, Copilot Analyst Agent, Artificial Intelligence (AI), Microsoft 365 Copilot, Operational Excellence, Creative Problem Solving, Strategic Communications, Dashboards, ChatGPT, Prompt Engineering, Lean Six Sigma, Six Sigma Green Belt, Kaizen, Product Management, Market Analysis"
    },
    {
        "first_name": "Moritz",
        "last_name": "Hoffmann",
        "headline": "PhD Candidate @ Bosch x TU B | MHP | Viessmann | Deloitte",
        "org_name": "EY-Parthenon",
        "location": "Stuttgart, Baden-Württemberg, Germany",
        "linkedin": "https://www.linkedin.com/in/moritz-hoffmann-7a37b319a",
        "amount": 6500.0,
        "title": "Moritz Hoffmann - EY-Parthenon - Senior Consultant",
        "summary": "Senior Consultant at EY-Parthenon. Located in Stuttgart, Baden-Württemberg, Germany.",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/moritz-hoffmann-7a37b319a\\nHeadline: PhD Candidate @ Bosch x TU B | MHP | Viessmann | Deloitte\\nConnections: 907 | Followers: 908\\nLocation: Stuttgart, Baden-Württemberg, Germany\\n\\nExperience:\\n- Senior Consultant @ EY-Parthenon (Feb 2026 - Present)\\n- Technical Project Manager, Aftermarket Development Division @ Bosch (Jul 2023 - Jan 2026)\\n- Executive Assistant @ MHP - A Porsche Company (Apr 2022 - Sep 2022)\\n- Research assistant with academic degree @ ESB Business School, Reutlingen University (Jan 2022 - Feb 2022)\\n- Student assistant in the field of accounting and controlling @ Technische Hochschule Mittelhessen (Oct 2020 - Mar 2021)\\n- Bachelor thesis student @ Viessmann (Dec 2020 - Feb 2021)\\n\\nEducation:\\n- Technische Universität Berlin (Dr.-Ing. - Control and system theory)\\n- Stellenbosch University (Master of Engineering - MEng - Engineering Management (Research))\\n- ESB Business School, Reutlingen University (Master of Science - MS/ Master of Engineering - MEng - Digital Industrial Management and Engineering)\\n- Technische Hochschule Mittelhessen (Bachelor of Science - BS - Industrial Engineering)\\n\\nCertifications:\\n- Qualitätsmanagement-Beauftragter (TÜV SÜD)\\n- Qualitätsmanagement-Fachkraft (TÜV SÜD)\\n\\nTop Skills:\\nKomplexe systeme, Diskrete Fertigung, Multi-Agenten Systeme, Produktionsplanung und Steuerung, Forschung und Entwicklung (F&E), Remanufacturing, Automotive Aftermarket, Elektrische Lenksysteme, Digitale Produktentwicklung, Fabrikplanung, Kostenkalkulation, Produktionstechnologien, Traceability, Radio-Frequency Identification (RFID), Echtzeitdaten"
    },
    {
        "first_name": "Luiz",
        "last_name": "Sol",
        "headline": "Senior Consultant at Bain & Company | ex-CTO/Partner in Quant Asset Mgmt | LBS MBA | PE DD, Value Creation, Digital & Data",
        "org_name": "Bain & Company",
        "location": "London, England, United Kingdom",
        "linkedin": "https://www.linkedin.com/in/luizsol",
        "amount": 6500.0,
        "title": "Luiz Sol - Bain & Company - Senior Consultant",
        "summary": "Strategy consultant at Bain in London with a hybrid background across technology leadership and investing. I work at the intersection of strategy, analytics, and execution—turning complex, ambiguous problems into practical decisions and implementable",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/luizsol\\nHeadline: Senior Consultant at Bain & Company | ex-CTO/Partner in Quant Asset Mgmt | LBS MBA | PE DD, Value Creation, Digital & Data\\nConnections: 2336 | Followers: 2346\\nLocation: London, England, United Kingdom\\n\\nAbout:\\nStrategy consultant at Bain in London with a hybrid background across technology leadership and investing. I work at the intersection of strategy, analytics, and execution—turning complex, ambiguous problems into practical decisions and implementable operating plans.\n\nBefore Bain, I was CTO & Partner at an investment firm, leading system/infrastructure overhauls, cloud migrations, team build-out, and supporting M&A processes. I’m an engineer by training (USP) and hold the CQF, which shapes how I approach problem-solving and quantitative work.\n\nFocus areas:\n• Growth & strategy (market / competitive mapping, business planning)\n• Digital, data & AI-enabled operating models\n• Performance improvement / reliability / cost-out\n• Financial modeling, valuation, decision analysis\n• Private equity diligence & value creation (where relevant)\n\nAlways happy to connect with people working on digital strategy, investing, and transformation.\\n\\nExperience:\\n- Senior Consultant @ Bain & Company (Jul 2026 - Present)\\n- Consultant @ Bain & Company (Aug 2025 - Jun 2026)\\n- Summer Associate @ Bain & Company (Jun 2024 - Aug 2024)\\n- Chief Technology Officer (CTO) & Partner @ Pandhora Investimentos (Aug 2021 - Jul 2023)\\n- Head Of Technology @ Atlas One Investimentos (Jul 2019 - Aug 2021)\\n- Software Development Intern @ Atlas One Investimentos (Jan 2018 - Jun 2019)\\n\\nEducation:\\n- London Business School (MBA - Business Administration and Management)\\n- Yale School of Management (MBA (Exchange / Visiting MBA Student) - Business Administration and Management)\\n- Escola Politécnica da USP (Bachelor of Engineering (B.Eng.) - Electrical and Electronics Engineering)\\n- Politecnico di Torino (Exchange Program - Electrical and Electronics Engineering)\\n\\nCertifications:\\n- Leveraged Buyout (LBO) Modeling (Wall Street Prep)\\n- Certificate in Quantitative Finance (Fitch Learning)\\n- Google Project Management Professional Certificate (Coursera)\\n\\nTop Skills:\\nMicrosoft Excel, Financial Analysis, Deal Sourcing, Information Technology, Mentoring, System Architecture, Asset Management, Databases, Management, Team Management, Finance, Project Management, Python, Statistics, Economics"
    },
    {
        "first_name": "Wang",
        "last_name": "Shuhui",
        "headline": "Indirect Sourcing Manager",
        "org_name": "ByteDance",
        "location": "Singapore",
        "linkedin": "https://www.linkedin.com/in/wang-shuhui",
        "amount": 6500.0,
        "title": "Wang Shuhui - ByteDance - Indirect Sourcing Manager - Supply Chain services,",
        "summary": "I am well equipped with over 15 years of corporate experience, majority of it in procurement related work and cross functional projects in both established MNC environment and start up environment in both tech and logistics industry. Vast experience ",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/wang-shuhui\\nHeadline: Indirect Sourcing Manager\\nConnections: 285 | Followers: 301\\nLocation: Singapore\\n\\nAbout:\\nI am well equipped with over 15 years of corporate experience, majority of it in procurement related work and cross functional projects in both established MNC environment and start up environment in both tech and logistics industry. Vast experience in procurement / contract management / negotiation / vendor management / outsourcing/logistics & supply chain.\\n\\nExperience:\\n- Indirect Sourcing Manager - Supply Chain services, Data Center @ ByteDance (Nov 2024 - Present)\\n- Global Outsourcing Program Manager @ Meta (Mar 2022 - Apr 2024)\\n- VP, Regional Procurement @ Ninja Van (Jul 2020 - Mar 2022)\\n- Regional Procurement Manager @ Ninja Van (Apr 2019 - Jun 2020)\\n- Regional Procurement Manager, Damco @ A.P. Moller - Maersk (Jun 2017 - Feb 2019)\\n- Assistant Manager, contract management @ A.P. Moller - Maersk (Dec 2010 - Jun 2017)\\n\\nEducation:\\n- RMIT University\\n\\nTop Skills:\\nGlobal Sourcing, Procurement, Supplier Negotiation, Cost Reduction, Contract Negotiation, Business Process Outsourcing (BPO), Program Management, Stakeholder Management, Stakeholder Engagement, Cross-functional Coordination, Cross-Cultural Communication Skills, Trucking, Supply Chain Management, Container, Shipping"
    },
    {
        "first_name": "Christopher",
        "last_name": "Hsu",
        "headline": "Senior Sourcing Manager at Lidl & Kaufland Asia Pte. Limited",
        "org_name": "Lidl & Kaufland Asia",
        "location": "Singapore, Singapore",
        "linkedin": "https://www.linkedin.com/in/christopher-hsu-13a5b3103",
        "amount": 6500.0,
        "title": "Christopher Hsu - Lidl & Kaufland Asia - Senior Sourcing Manager",
        "summary": "I am experienced in manufacturing and end-to-end sales. I have worked in Vietnam from 2015-2022, and am familiar with manufacturing industry there.",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/christopher-hsu-13a5b3103\\nHeadline: Senior Sourcing Manager at Lidl & Kaufland Asia Pte. Limited\\nConnections: 2314 | Followers: 2345\\nLocation: Singapore, Singapore\\n\\nAbout:\\nI am experienced in manufacturing and end-to-end sales. I have worked in Vietnam from 2015-2022, and am familiar with manufacturing industry there.\\n\\nExperience:\\n- Senior Sourcing Manager @ Lidl & Kaufland Asia (Feb 2025 - Present)\\n- Operations Manager @ Lidl & Kaufland Asia (Sep 2022 - Jan 2026)\\n- QC Manager @ Vietnam Fortress Tools JSC (Feb 2020 - Aug 2022)\\n- Sales Executive @ Vietnam Fortress Tools JSC (Jan 2018 - Aug 2022)\\n- Assistant Account Executive @ Vietnam Formosa Tools Co., Ltd. (Aug 2015 - Jan 2018)\\n- Administrative Assistant @ Formosa Tools Co., Ltd. (Mar 2015 - Jan 2018)\\n\\nEducation:\\n- UC Irvine (Bachelor’s Degree - Biology/Biological Sciences, General)\\n\\nTop Skills:\\nBusiness Development, Sales Management, Operational Excellence, Manufacturing Operations Management, Sales, Management, Design, Client Relations, Business Management, New Business Development, Business Negotiation, Commercial Products, Industrial Sales, Consumer Research, Knowledge Graphs"
    },
    {
        "first_name": "Nicolas Z.",
        "last_name": "Tan",
        "headline": "Robotics Multimodal Engineer | Ex-NVIDIA | Mechanical Design (ASME Y14.5) & Software (ML, ROS2, Computer Vision, Embedded) & PCB Design | Data Center x MedTech x Humanoid | Northeastern University | Product Strategy",
        "org_name": "Analog Devices",
        "location": "Redwood City, California, United States",
        "linkedin": "https://www.linkedin.com/in/nicolas-tan",
        "amount": 6500.0,
        "title": "Nicolas Z. Tan - Analog Devices - Future R&D Intiatives, Robotics Software Engineer",
        "summary": "Nicolas is a multifaceted Robotics Engineer. His specialty is robust in Sensor Development, Cameras, Semiconductor & Consumer Devices, particularly focused on Product Development. \n\nCoupled Mechanical Engineering with Software Engineering, with Elect",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/nicolas-tan\\nHeadline: Robotics Multimodal Engineer | Ex-NVIDIA | Mechanical Design (ASME Y14.5) & Software (ML, ROS2, Computer Vision, Embedded) & PCB Design | Data Center x MedTech x Humanoid | Northeastern University | Product Strategy\\nConnections: 3637 | Followers: 4328\\nLocation: Redwood City, California, United States\\n\\nAbout:\\nNicolas is a multifaceted Robotics Engineer. His specialty is robust in Sensor Development, Cameras, Semiconductor & Consumer Devices, particularly focused on Product Development. \n\nCoupled Mechanical Engineering with Software Engineering, with Electrical Engineering minor at Northeastern University, he aspires to contribute his talents while continually learning from others! \n\nHis current keen interests are within the field of Management Consulting, Product Management, Medical/Surgical Devices (Embedded Systems, R&D, Control Systems, Mechanical Engineering), Regulatory Affairs (FDA), Autonomous Robotics & Vehicles, Venture Capital/Private Equity, Investment Banking, Data Science and Agentic AI Technology!\n\n🤝Curious about his work? Read more below for his specialties, and feel free to reach out.\nhttp://nicolastan.tilda.ws/\n\n🤖⚡️\n•SolidWorks (FMEA, Simulations) / AutoCAD\n•Python (Focus: EDA, Robot Operating System - RViz/Gazebo)\n• Analog / Mixed Signal Circuits (PCB Design: Altium, PSpice, Computational Analyses)\n•3D Printing (SLA, FDM)\n•Signal Processing (Discrete, Filters)\n•V&V/R&D Testing \n•Systematic Control & Design for IOT Devices (Arduino, Raspberry Pi)\n\n🖥\n• Embedded C (DMA, OOD, Firmware Device Driver Development, Zephyr RTOS)\n•Python (Motor Controls, Sensor Fusion (RGBD/ToD Camera, Tactile Sensing, Robotic Manipulation, OOD, Pandas, Numpy, Matplotlib, Scikit Learn, Scipy, Tensorflow, Tensorflow Lite, OpenCV YOLOv7, OpenPose, ML/DL Fundamentals)\n• LLM RAG, Laanchain, Transformers, Vector Embedding Frameworks \n•MATLAB (incl. Simulink, Stateflow, Simscape)\n•Java\n•PowerBI / Tableau\n•Microsoft Suite Office (esp. Excel)\n•MS-SQL (Database Design and Management)\n\n📚\n•Regulatory Standards (ISO, IEC, EMC, cGMP, QMS Standards, etc)\n•Medical Device Product Development Ecosystem\n•Deal Sourcing and Due Diligence \n•Built Knowledge in Minimally Invasive Surgical Robotics: “Bronchoscopy, Colorectal, Bariatrics, Gynecology) & Artificial Heart Implants (LVAD, BiVAD, TAH, Pump Drivers; Pacemakers), etc\\n\\nExperience:\\n- Future R&D Intiatives, Robotics Software Engineer & AI Research - Tactile Multimodal Embodiment @ Analog Devices (Oct 2025 - Present)\\n- Senior Electrical Engineer, GPU Architecture - AI Data Centers/Hyperscalars  @ NVIDIA (Apr 2025 - Sep 2025)\\n- NPD Mechanical Design Engineer, Robotic Devices @ Johnson & Johnson (Jul 2024 - Jan 2025)\\n- Software Engineer - Cameras & Sensors Design @ Johnson & Johnson (Oct 2023 - Jul 2024)\\n- Professional development @ Career Break (Jul 2023 - Oct 2023)\\n- Product Strategy, Systems Integration Engineer III - Development & Supplier Quality @ Intuitive (Nov 2022 - Jul 2023)\\n\\nEducation:\\n- Northeastern University (Bachelor of Science - BS - Biomedical Robotics Engineering)\\n- Stanford University (Post-Bach - Computer Science)\\n- College of San Mateo (Continuing Studies - Computer Engineering)\\n- Stanford University (Summer - Institute for Computational & Mathematical Engineering (ICME) - Data Science)\\n\\nCertifications:\\n- CS62: Advanced Python (Stanford University)\\n- Object Oriented Data Structures in C++ (Coursera Course Certificates)\\n- Certified Solidworks Mechanical Designer (CSWA) (SolidWorks Designer)\\n- Control Design Onramp (MATLAB Coding)\\n- Simulink Onramp (MATLAB Coding)\\n- MATLAB Essentials: Onramp (MATLAB Coding)\\n\\nTop Skills:\\nDeep Reinforcement Learning, Humanoid, Gait Analysis, Generative AI, Computer Vision, Artificial Intelligence (AI), Product Management, DICOM, Image Segmentation, Articulation, Technology Transfer, Calibration, Component Design and Analysis, Modeling and Simulation, Injection Molding"
    },
    {
        "first_name": "Edie",
        "last_name": "Enders, MBA",
        "headline": "Procurement Transformation & EBITDA Improvement | Strategic Sourcing | Vendor Governance | Contract Negotiations | Private Equity Value Creation | Former Deloitte",
        "org_name": "Strategic Sourcing Consultants, LLC",
        "location": "Marietta, Georgia, United States",
        "linkedin": "https://www.linkedin.com/in/edieenders",
        "amount": 6500.0,
        "title": "Edie Enders, MBA - Strategic Sourcing Consultants, LLC - Founder & Principal Consultant",
        "summary": "As Founder and Principal Consultant of Strategic Sourcing Consultants, I deliver strategic sourcing and vendor management solutions tailored to diverse organizational needs. My expertise lies in developing and implementing sustainable procurement str",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/edieenders\\nHeadline: Procurement Transformation & EBITDA Improvement | Strategic Sourcing | Vendor Governance | Contract Negotiations | Private Equity Value Creation | Former Deloitte\\nConnections: 1111 | Followers: 1128\\nLocation: Marietta, Georgia, United States\\n\\nAbout:\\nAs Founder and Principal Consultant of Strategic Sourcing Consultants, I deliver strategic sourcing and vendor management solutions tailored to diverse organizational needs. My expertise lies in developing and implementing sustainable procurement strategies, managing RFx execution, and  staff augmentation services.  \n\nMy mission is to empower organizations with innovative approaches to procurement and vendor governance while serving as a trusted subject matter expert and advisor. With a focus on solving complex challenges and driving operational efficiency, I bring value through strategic programs and a commitment to sustainability in procurement practices.\\n\\nExperience:\\n- Founder & Principal Consultant @ Strategic Sourcing Consultants, LLC (Dec 2012 - Present)\\n- HRIS Strategic Sourcing Consultant (ELE Management Consulting) @ Wake Forest Baptist Health (Jan 2017 - Sep 2017)\\n- HR Global Strategic Sourcing Consultant (ELE Management Consulting) @ Newell Brands (Apr 2015 - Jun 2016)\\n\\nEducation:\\n- Penn State University (MBA - Logistics & Finance)\\n- Michigan State University (BS - Business/Managerial Economics)\\n- Université libre de Bruxelles (MBA Exchange Program - International Business/Trade/Commerce)\\n\\nCertifications:\\n- The New Age of Risk Management Strategy for Business (LinkedIn)\\n- Project Management Professional (PMP) (Project Management Institute)\\n- Cert Prep: Project Management Professional (PMP)® (LinkedIn)\\n- Artificial Intelligence for Project Managers (LinkedIn)\\n- Learning Watson Analytics (LinkedIn)\\n- MBA (The Pennsylvania State University )\\n\\nTop Skills:\\nSustainable Procurement, Strategic Programs, Finance, Contractual Obligations, Contractual, Zero-based Budgeting, Independent Contractors, Freelancing, Marketing, eSourcing, Platform as a Service (PAAS), Talent Management, Cloud Computing, Customer Relationship Management (CRM), Consulting"
    },
    {
        "first_name": "Rose Ann",
        "last_name": "F.",
        "headline": "Senior Buyer & Global Sourcing | Fashion | Footwear, SLG, Accessories & Socks | Building Strategic Partnerships with International Brands | Supplier Networks & Growth",
        "org_name": "BFL Group",
        "location": "Dubai, United Arab Emirates",
        "linkedin": "https://www.linkedin.com/in/rose-ann-f-732395197",
        "amount": 6500.0,
        "title": "Rose Ann F. - BFL Group - Senior Buyer | Strategic Sourcing",
        "summary": "Strategic Buying & Sourcing professional with 13 years of experience across Footwear, Small Leather Goods (SLG), Apparel, Socks, Accessories, Cosmetics, Fragrances, FMCG, and Toys. Proven track record in strategic sourcing, category management, produ",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/rose-ann-f-732395197\\nHeadline: Senior Buyer & Global Sourcing | Fashion | Footwear, SLG, Accessories & Socks | Building Strategic Partnerships with International Brands | Supplier Networks & Growth\\nConnections: 2244 | Followers: 2284\\nLocation: Dubai, United Arab Emirates\\n\\nAbout:\\nStrategic Buying & Sourcing professional with 13 years of experience across Footwear, Small Leather Goods (SLG), Apparel, Socks, Accessories, Cosmetics, Fragrances, FMCG, and Toys. Proven track record in strategic sourcing, category management, product development, vendor management, and commercial negotiations across global supply markets.\n\nSkilled in building supplier partnerships, sourcing strategies, optimizing product assortments, managing P&L, and driving category growth through data-driven decision-making. Experienced in off-price retail buying, brand collaborations, cost optimization, and delivering profitable commercial results while aligning with business objectives.\\n\\nExperience:\\n- Senior Buyer | Strategic Sourcing @ BFL Group (Jan 2014 - Present)\\n- General Manager | Hotel and Tourism Management @ Richers Club Corp (Jan 2011 - Dec 2013)\\n- Business Development Consultant @ Bayan Telecommunications (Jan 2010 - Dec 2010)\\n- Casino Dealer | Operations @ PAGCOR (Philippine Amusement and Gaming Corporation) (Jan 2009 - Dec 2009)\\n- Corporate Account Specialist @ Globe Telecommunications, Inc. (Jan 2008 - Dec 2008)\\n\\nCertifications:\\n- Business Data Analytics (Formatech)\\n- Certified International Purchasing/Procurement Manager  (CIPM) (IPSCMI)\\n- Certified International Purchasing/Procurement Professional(CIPP) (IPSCMI)\\n\\nTop Skills:\\nNew Business Development, High-rise account management, Sales Strategy & Execution, Negotation & Closing, Business Strategy, Corporate Sales, Business Development, Telecom solutions, Customer service, Market Research Project Management, CRM systems, Excellent customer service, Strong mathematical abilities, accurately calculating bets and managing chip transactions., Keen attention to detail"
    },
    {
        "first_name": "Jenny",
        "last_name": "Jing",
        "headline": "Global Procurement & Supply Chain, Strategic Sourcing  | Trade Compliance · Transportation & Network Strategy | AI-Powered Procurement | Multi-Category Hardware | Founder of Chain Reaction",
        "org_name": "ByteDance",
        "location": "Los Angeles Metropolitan Area",
        "linkedin": "https://www.linkedin.com/in/huijunjing",
        "amount": 12500.0,
        "title": "Jenny Jing - ByteDance - Global eCommerce Supply Chain and Global Sourcing",
        "summary": "I build and lead the supply-chain, logistics, and sourcing engines behind large-scale operations \n\nOver 13+ years across ByteDance/TikTok, LONGi Solar, and BYD, I've led global logistics and multi-category hardware sourcing for operations where on-ti",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/huijunjing\\nHeadline: Global Procurement & Supply Chain, Strategic Sourcing  | Trade Compliance · Transportation & Network Strategy | AI-Powered Procurement | Multi-Category Hardware | Founder of Chain Reaction\\nConnections: 1046 | Followers: 1078\\nLocation: Los Angeles Metropolitan Area\\n\\nAbout:\\nI build and lead the supply-chain, logistics, and sourcing engines behind large-scale operations \n\nOver 13+ years across ByteDance/TikTok, LONGi Solar, and BYD, I've led global logistics and multi-category hardware sourcing for operations where on-time delivery and cost decide whether programs succeed: • Own $500M+ in annual third-party logistics spend across 20+ partners and carriers — and the logistics P&L behind it • Designed a 17-site national distribution network and cut delivery from 5 days to 3 while reducing last-mile cost ~20% • Directed ~$3B in multi-category hardware deployment and helped land a $9B transportation-infrastructure capital project • Led multi-site teams of 35+ across the U.S., Canada, and offshore • Own trade compliance end to end — customs, HTS classification, duty optimization, FTZ, and forced-labor (UFLPA) traceability\nWhat sets me apart: I'm an AI-first operator. I build multi-agent AI / Claude-powered sourcing workflows and an AI contract system that automate RFx, standardize commercial terms, and turn operational risk into stronger agreements — real velocity for procurement.\nI also track the forces reshaping global trade: I write Chain Reaction, an ongoing analysis of tariffs and global trade dynamics — because right now, landed cost and duty strategy are where programs are won or lost.\nMy toolkit spans transportation and network strategy, multi-category hardware sourcing (data-center-adjacent, EV, battery, motors, solar, energy storage), commercial negotiation and MSAs, trade compliance, freight P&L and S&OP, supplier performance, TMS/WMS and visibility systems, and data-driven decision-making (Python, dashboards).\nI lead with data and a single source of truth, influence cross-functional partners and leadership without direct authority, and build the frameworks that keep ambitious scaling on track — because the real cost is in what you don't see.\\n\\nExperience:\\n- Global eCommerce Supply Chain and Global Sourcing @ ByteDance (Oct 2023 - Present)\\n- Head of Logistics, Operations  @ LONGi Solar (Oct 2022 - Oct 2023)\\n- Director Of Operations @ BYD (Oct 2020 - Oct 2022)\\n- Project Development Management @ BYD (May 2018 - Aug 2022)\\n- Director of Procurement & Logistics @ BYD (Jun 2016 - May 2018)\\n- Procurement & Logistic Manager @ BYD (Sep 2013 - Jun 2016)\\n\\nEducation:\\n- UCLA (Certificate in Supply Chain Managememt - Logistics)\\n- The University of Toledo (Master of Arts (M.A.) - Applied Economics)\\n- The University of Toledo (Bachelor of Business Administration (B.B.A.) - International Business & Marketing)\\n- Beijing Institute of Technology (Bachelor's degree - International Business)\\n\\nCertifications:\\n- The Science of Well Being  (Yale University)\\n- Lean Six Sigma Black Belt Certification (Management & Strategy Institute)\\n- Project Management Essentials Certified (PMEC) (Management & Strategy Institute)\\n- Regulatory Affairs Certification (RAC): Medical Device  (Regulatory Affairs Professionals Society (RAPS))\\n- Medical Device Postmarket Surveillance (Regulatory Affairs Professionals Society (RAPS))\\n- Medical Device-Corrections, Removals and Recalls (Regulatory Affairs Professionals Society (RAPS))\\n\\nTop Skills:\\nCritical Thinking, Negotiation, Public Speaking, Leadership, Management, Strategic Planning, Analytical Skills, E-Commerce, Order Fulfillment, Middle Mile, Last Mile, Reverse Logistics, Stakeholder Management, International Trade, Free Trade Agreements"
    },
    {
        "first_name": "Olivia",
        "last_name": "Ferris",
        "headline": "Principal Data Consultant at Talent Insights - dbt | Databricks | Snowflake",
        "org_name": "Talent Insights Group",
        "location": "Sydney, New South Wales, Australia",
        "linkedin": "https://www.linkedin.com/in/olivia-ferris-0531a3170",
        "amount": 6500.0,
        "title": "Olivia Ferris - Talent Insights Group - Principal Data Recruitment Consultant",
        "summary": "Hi 👋\nI’m Livv, a Principal Data Recruitment Consultant for Talent Insights, Australia's Trusted Data Recruitment Partner.\n\nI work in Analytics, Data & AI Engineering, helping brilliant people and innovative companies find each other (basically, a pro",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/olivia-ferris-0531a3170\\nHeadline: Principal Data Consultant at Talent Insights - dbt | Databricks | Snowflake\\nConnections: 22123 | Followers: 22798\\nLocation: Sydney, New South Wales, Australia\\n\\nAbout:\\nHi 👋\nI’m Livv, a Principal Data Recruitment Consultant for Talent Insights, Australia's Trusted Data Recruitment Partner.\n\nI work in Analytics, Data & AI Engineering, helping brilliant people and innovative companies find each other (basically, a professional matchmaker for data wizards). I specialise in the modern analytics and open-source stack: think dbt, Databricks, Snowflake, Looker, Omni and BigQuery to name a few.\n\nIf you’re in this world (whether you’re contemplating your next career move or building a World-Cup winning team of data pros) let’s connect. I probably know the perfect candidate before you’ve finished saying \"chatGPT”. \n\nWhen I’m not recruiting, I'm dancing, swimming or playing netball. I also tinker on the piano, sign myself up for adrenaline-packed adventures and I’m learning SQL so I can finally laugh at all the inside jokes my candidates keep making. 😆 \n\n📧 Olivia@talentinsights.com.au\\n\\nExperience:\\n- Principal Data Recruitment Consultant @ Talent Insights Group (Aug 2025 - Present)\\n- Senior Data Recruitment Consultant @ Talent Insights Group (Aug 2022 - Aug 2025)\\n- Recruitment Specialist @ Railsbank (Nov 2021 - Aug 2022)\\n- Senior Data Recruitment Consultant @ Venturi Ltd (Sep 2018 - Nov 2021)\\n- Data Analyst @ Vodafone (Aug 2016 - Oct 2016)\\n\\nEducation:\\n- Loughborough University (Bachelor's degree - English Language and Literature, General)\\n- Leek High School\\n\\nCertifications:\\n- Entry level sign (british-sign.co.uk - Online British Sign Language Training)\\n- Grade 8 piano (ABRSM)\\n- Grade 4 Tap | Gold Bar 3 Disco (Royal Academy of Dance)\\n- Qualified Zumba Instructor (Zumba)\\n\\nTop Skills:\\nRelationship Building, Communication, Sales Processes, Consultative Selling, Business-to-Business (B2B), Product Knowledge, Customer Experience, Sales, Business Development, Recruiting, Account Management, Stakeholder Management, Talent Management, Sourcing, Onboarding"
    },
    {
        "first_name": "Graeme",
        "last_name": "Cox",
        "headline": "Principal Recruitment Consultant | Head Contractor Solutions | Day Rate Contractor Recruitment across IT, Business Change, Data, AI & Digital Recruitment",
        "org_name": "Head Resourcing",
        "location": "Edinburgh, Scotland, United Kingdom",
        "linkedin": "https://www.linkedin.com/in/graemecox",
        "amount": 12500.0,
        "title": "Graeme Cox - Head Resourcing - Principal Recruitment Consultant",
        "summary": "Head Resourcing supplies Contract & Permanent Technology, Business Change, Data, AI & Digital staff to companies throughout the UK & have been committed to providing our clients & candidates with excellent customer service since 2001.\n\nPrincipal Recr",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/graemecox\\nHeadline: Principal Recruitment Consultant | Head Contractor Solutions | Day Rate Contractor Recruitment across IT, Business Change, Data, AI & Digital Recruitment\\nConnections: 6268 | Followers: 6804\\nLocation: Edinburgh, Scotland, United Kingdom\\n\\nAbout:\\nHead Resourcing supplies Contract & Permanent Technology, Business Change, Data, AI & Digital staff to companies throughout the UK & have been committed to providing our clients & candidates with excellent customer service since 2001.\n\nPrincipal Recruitment Consultant with nearly 20 years' Contractor Recruitment experience delivering to & partnering with a range of Financial Services, Technology, Public Sector and SME Clients across Scotland and the UK. \nAlways a consistent performer with a track record of achieving Annual Targets. I aspire to make recruitment as straightforward as possible, cutting through some of the \"noise\" our industry is known for.\nWhat you see is what you get. I say it a lot in conversations but \"we're here to try & help\" at the end of the day. \n\nContractor Roles supported include (but are not limited to) - Business Analysis, Project Management, Programme Management, PMO, Change Management, Testing, Automation, Software Development, Data Analysis, AI/ML, DevOps, Infrastructure, IT Support, Architecture (Technical, Solutions, Enterprise) and \"Head of\" positions.\\n\\nExperience:\\n- Principal Recruitment Consultant @ Head Resourcing (Jun 2021 - Present)\\n- Senior Recruitment Consultant (Contracts) @ Head Resourcing (Feb 2011 - Jun 2021)\\n- Recruitment Consultant @ Head Resourcing (Dec 2006 - Feb 2011)\\n- Various Part-time roles @ Various... (Aug 2000 - Aug 2006)\\n\\nEducation:\\n- University of Stirling (BA (Hons) 2:1 - Business Studies)\\n- Bo'ness Academy\\n\\nTop Skills:\\nProject Resourcing, Candidate Selection, Business Relationship Management, Contract Recruitment, Stakeholder Management, Candidate Generation, IT Recruitment, Executive Search, Recruitment Advertising, Technical Recruiting, Human Resources, Screening Resumes, Permanent Placement, SDLC, Recruiting"
    },
    {
        "first_name": "Jing",
        "last_name": "H.",
        "headline": "Product Sourcing | Supply Chain Data Consultant | Helping importers worldwide source from China safely — supplier vetting, landed cost modelling & trade compliance | 10yr Data Expert + 5yr Alibaba International Trade",
        "org_name": "SourceChina Pantners",
        "location": "London Area, United Kingdom",
        "linkedin": "https://www.linkedin.com/in/jing-h-6ba5a3a",
        "amount": 6500.0,
        "title": "Jing H. - SourceChina Pantners - Product Sourcing & Supply Chain Data Consultanting",
        "summary": "🚨 Most businesses sourcing from China are losing money — and they don't even know it.\n\nWrong HS codes. Undisclosed anti-dumping duties. Miscalculated landed costs. Single-supplier dependency. Post-Brexit compliance gaps. Unreliable lead times with no",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/jing-h-6ba5a3a\\nHeadline: Product Sourcing | Supply Chain Data Consultant | Helping importers worldwide source from China safely — supplier vetting, landed cost modelling & trade compliance | 10yr Data Expert + 5yr Alibaba International Trade\\nConnections: 480 | Followers: 464\\nLocation: London Area, United Kingdom\\n\\nAbout:\\n🚨 Most businesses sourcing from China are losing money — and they don't even know it.\n\nWrong HS codes. Undisclosed anti-dumping duties. Miscalculated landed costs. Single-supplier dependency. Post-Brexit compliance gaps. Unreliable lead times with no early warning system.\n\nThese aren't bad luck. They're data problems. And data problems have data solutions.\n\nI've spent 10 years as a data consultant and 5 years running my own international trade business on Alibaba.com — selling print products to buyers across Europe, North America, Asia-Pacific, and beyond. I've been on both sides of the table: the analyst who builds the models and the importer who lives the consequences when the numbers are wrong.\n\nThat combination is rare. And it's exactly what most importers need.\n\n━━━━━━━━━━━━━━━━━━━━━\n𝗪𝗵𝗮𝘁 𝗜 𝗵𝗲𝗹𝗽 𝘆𝗼𝘂 𝘄𝗶𝘁𝗵:\n━━━━━━━━━━━━━━━━━━━━━\n✅ Supplier vetting & risk scoring — objective data-driven assessment of any Alibaba or overseas supplier before you commit a penny\n✅ True landed cost modelling — the real unit cost including freight, duty, anti-dumping measures, VAT, FX margin and last-mile delivery\n✅ HS code verification & tariff compliance — correct commodity codes, anti-dumping duty checks, post-Brexit UK/EU tariff analysis\n✅ Demand forecasting & inventory optimisation — how much to order and when, based on your sales data — not guesswork\n✅ Supply chain risk monitoring — monthly dashboards tracking supplier health, freight markets, currency movements and compliance\n✅ Supplier performance analytics — data to renegotiate from strength at every contract renewal\n\n━━━━━━━━━━━━━━━━━━━━━\n𝗪𝗵𝗼 𝗜 𝘄𝗼𝗿𝗸 𝘄𝗶𝘁𝗵:\n━━━━━━━━━━━━━━━━━━━━━\nSMEs and growing businesses worldwide who import from China or South East Asia — particularly in e-commerce, retail, manufacturing, wholesale and distribution. I work remotely with clients across the UK, Europe, North America, Australia and beyond.\n\n━━━━━━━━━━━━━━━━━━━━━\n𝗠𝘆 𝗯𝗮𝗰𝗸𝗴𝗿𝗼𝘂𝗻𝗱:\n━━━━━━━━━━━━━━━━━━━━━\n🔹 10 years data consulting — analytics, business intelligence, risk modelling, forecasting, data strategy\n🔹 5 years running an Alibaba.com international export business — print products, global buyers, real supply chain operations\n🔹 UK & EU import regulations,  HS commodity codes, Incoterms, Trade Assurance, and China sourcing\n\n━━━━━━━━━━━━━━━━━━━━━\n𝗪𝗼𝗿𝗸 𝘄𝗶𝘁𝗵 𝗺𝗲:\n━━━━━━━━━━━━━━━━━━━━━\n📋 Supplier Audit Report \n📊 Landed Cost Modelling & Margin Analysis \n🔍 Supply Chain Optimisation Project \n📅 Monthly Supply Chain Risk Retainer \n📦 Full Supply Chain Optimisation\\n\\nExperience:\\n- Product Sourcing & Supply Chain Data Consultanting @ SourceChina Pantners (Jun 2026 - Present)\\n- International Trade Business Owner — Alibaba.com @ Guangzhou JINGLI Printing equipment Co., ltd. (Mar 2023 - Present)\\n- Data Consultant @ Bank of Ireland (Dec 2022 - Mar 2023)\\n- Data Consultant @ Bank of Ireland (Mar 2022 - Dec 2022)\\n- Data Consultant @ Grant Thornton UK LLP (Nov 2021 - Jan 2022)\\n- Marketing Automation Manager/Data Consultant @ Global Blue (Apr 2019 - Aug 2020)\\n\\nCertifications:\\n- DBS Certificate (Disclosure and Barring Service (DBS))\\n- IBM Certified Professional - Python Data Science (IBM)\\n- SAS Certified Advanced Programmer for SAS 9 (SAS)\\n- SAS Certified Base Programmer for SAS 9 (SAS)\\n- MCTS: SQL Server 2005 (Microsoft)\\n- MCITP: Database Developer on SQL Server 2005 (Microsoft)\\n\\nTop Skills:\\nSME Consulting, International Trade, Landed Cost Analysis, Alibaba Sourcing, E-commerce Operations, Supply Chain Management, Supplier Vetting & Sourcing, Agile Methodologies, Analytical Skills, Pandas (Software), IRB, Hue, Credit Risk, Bhsiness analysis, Data Analytics"
    },
    {
        "first_name": "Marlin",
        "last_name": "Cox",
        "headline": "Data Center Planning and Delivery | Large-Scale Construction & AI Data Centers | 15+ Years Leading Complex Programs",
        "org_name": "ChronoScale",
        "location": "Atlanta, Georgia, United States",
        "linkedin": "https://www.linkedin.com/in/marlin-cox-b2a068a",
        "amount": 6500.0,
        "title": "Marlin Cox - ChronoScale - Director - Data Center Planning and Delivery",
        "summary": "Director - Data Center Planning and Delivery at ChronoScale. Located in Atlanta, Georgia, United States.",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/marlin-cox-b2a068a\\nHeadline: Data Center Planning and Delivery | Large-Scale Construction & AI Data Centers | 15+ Years Leading Complex Programs\\nConnections: 1482 | Followers: 1507\\nLocation: Atlanta, Georgia, United States\\n\\nExperience:\\n- Director - Data Center Planning and Delivery @ ChronoScale (Jul 2026 - Present)\\n- AI Contracts Manager - Data Center Construction @ Microsoft (May 2023 - Jul 2026)\\n- Staff Global Supply Manager - Construction/Infrastructure @ Tesla (Jul 2020 - May 2023)\\n- EPC Strategic Sourcing and Category Manager @ Georgia-Pacific LLC (Mar 2014 - Jul 2020)\\n- Project Manager @ Winter Construction (Sep 2006 - Mar 2014)\\n\\nEducation:\\n- Georgia Institute of Technology (Master of Science - Building Construction)\\n- Berry College (Bachelor of Science - Environmental Science)\\n\\nTop Skills:\\nManagement, Negotiation, Microsoft Office, Construction Management, Value Engineering, Contract Management, CPM Scheduling, Project Estimation, Environmental Awareness, Pre-construction, Construction, Earned Value Management, Project Bidding, Engineering, Project Management"
    },
    {
        "first_name": "Aravind",
        "last_name": "Mohan",
        "headline": "Technical Sourcing Manager @ AWS | Data Center Infrastructure (Compute, Mechanical, Thermal, Racks & Storage).",
        "org_name": "Amazon Web Services (AWS)",
        "location": "Austin, Texas Metropolitan Area",
        "linkedin": "https://www.linkedin.com/in/aravind-mohan-6419733a",
        "amount": 6500.0,
        "title": "Aravind Mohan - Amazon Web Services (AWS) - Technical Sourcing Manager - Data Center Infrastru",
        "summary": "Procurement and Supply chain professional, with extensive contract negotiation experience. Strong analytical, organizational and interpersonal skills. Proven track record in category and project management. High capability to learn fast and to adapt ",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/aravind-mohan-6419733a\\nHeadline: Technical Sourcing Manager @ AWS | Data Center Infrastructure (Compute, Mechanical, Thermal, Racks & Storage).\\nConnections: 2358 | Followers: 2339\\nLocation: Austin, Texas Metropolitan Area\\n\\nAbout:\\nProcurement and Supply chain professional, with extensive contract negotiation experience. Strong analytical, organizational and interpersonal skills. Proven track record in category and project management. High capability to learn fast and to adapt to new situations. \n\nSpecialties\n* Strategic procurement\n* Category Management (HDD, Tape, Networking, Storage, On-Prem & SaaS, Facilities, Professional Services)\n* Contract Negotiation\n* Supplier Relationship Management\n* Project Management\n* E-Sourcing\\n\\nExperience:\\n- Technical Sourcing Manager - Data Center Infrastructure (Compute, Liquid Cooling, Racks & Storage). @ Amazon Web Services (AWS) (2020 - Present)\\n- Technology Sourcing Manager @ Visa (2018 - 2020)\\n- Sr. Global Commodity Manager - Networking Contract Manufacturing @ Dell (2017 - 2018)\\n- Global Sourcing Manager -  HDD @ Dell (2015 - 2017)\\n- Global Category Manager - Facilities and CapEx @ Dell (2014 - 2015)\\n- MBA Student Consultant - Contingent Labor @ Dell (2013 - 2014)\\n\\nEducation:\\n- W. P. Carey School of Business – Arizona State University (Master of Business Administration (M.B.A.) - Supply Chain Management)\\n- W. P. Carey School of Business – Arizona State University (Master of Science (MS) - Concurrent Degree - Information Management)\\n- Anna University Chennai (Bachelor of Engineering (B.E.) - Production)\\n\\nCertifications:\\n- Managing Virtual Teams (LinkedIn)\\n- Lean Foundations (LinkedIn)\\n- Negotiation Skills (LinkedIn)\\n\\nTop Skills:\\nProject Management, Supply Chain Management, Change Management, Strategy, Requirements Analysis, Procurement, Business Analysis, SDLC, Leadership, Data Analysis, Release Management, JCL, COBOL, Financial Analysis, Statistics"
    },
    {
        "first_name": "Francisco",
        "last_name": "Navarro",
        "headline": "Enterprise Agentic AI++ Board Advisor | EU AI Act | Sovereign AI | ERP, CTRM, Trading and Finance",
        "org_name": "Experio AI",
        "location": "Geneva Metropolitan Area",
        "linkedin": "https://www.linkedin.com/in/francoisnavarro",
        "amount": 12500.0,
        "title": "Francisco Navarro - Experio AI - Agentic AI & Decision Intelligence | EU AI Act | E",
        "summary": "VP EMEA — Quoreka\n\n Drive ARR/ACV growth across the EMEA region by targeting major trading houses and energy companies active in the physical metals, LNG, oil and gas markets, with complex sales cycles conducted directly at C-suite level (CEO, CFO, ",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/francoisnavarro\\nHeadline: Enterprise Agentic AI++ Board Advisor | EU AI Act | Sovereign AI | ERP, CTRM, Trading and Finance\\nConnections: 11969 | Followers: 21377\\nLocation: Geneva Metropolitan Area\\n\\nAbout:\\nVP EMEA — Quoreka\n\n Drive ARR/ACV growth across the EMEA region by targeting major trading houses and energy companies active in the physical metals, LNG, oil and gas markets, with complex sales cycles conducted directly at C-suite level (CEO, CFO, CRO, Head of Trading).\n Led the acquisition of new logo, deal structuring and the development of strategic accounts for Agentic Ai CTRM, ERP and SaaS+AI solutions incorporating agency capabilities for position monitoring, back-office reconciliation and hedging decision support within EMIR and MiFID II environments.\n Developed a market penetration model combining competitive positioning, value engineering and cross-sell/upsell expansion, drawing on an ecosystem of specialist partners (CTRM integrators, commodity consultants)\n***** Contact my private message or email : francisconavarro@bluewin.ch\\n\\nExperience:\\n- Agentic AI & Decision Intelligence | EU AI Act | EMEA Commodity Trading & Regulated Financial @ Experio AI (May 2026 - Present)\\n- Vice President of Sales, EMEA @ Quoreka (Jul 2021 - Apr 2026)\\n- Global Account Director @ SAP (Jun 2013 - Jul 2021)\\n- Global Account Manager Cloud integration @ IBM (Aug 2009 - Aug 2013)\\n- Sales Manager ERP Strategic Outsourcing @ IBM (2008 - 2009)\\n- Account Executive Sales Manager @ IBM (Apr 2000 - 2007)\\n\\nEducation:\\n- London Business School (Management - )\\n- IMD (Master's degree - Sales & Marketing)\\n- INSEAD (INSEAD Coaching Certificate - Vente spécialisée)\\n- The Boston Graduate School of Business (Management Business Administration - International Business)\\n\\nTop Skills:\\nManagement, Leadership, Negotiation, Strategic Thinking, Design Thinking, SaaS, Enterprise Software, Blockchain, SAP S/4HANA, Spanish, Italian, English, German, French, AI Strategy"
    },
    {
        "first_name": "Patrick",
        "last_name": "Caquel",
        "headline": "Enterprise Data Sales at Bloomberg",
        "org_name": "Bloomberg LP",
        "location": "New York, New York, United States",
        "linkedin": "https://www.linkedin.com/in/patrickcaquel",
        "amount": 6500.0,
        "title": "Patrick Caquel - Bloomberg LP - Enterprise Data Sales",
        "summary": "Enterprise Data Sales at Bloomberg LP. Located in New York, New York, United States.",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/patrickcaquel\\nHeadline: Enterprise Data Sales at Bloomberg\\nConnections: 736 | Followers: 745\\nLocation: New York, New York, United States\\n\\nExperience:\\n- Enterprise Data Sales @ Bloomberg LP (Aug 2022 - Present)\\n- Vice President - Market Data Sourcing @ Morgan Stanley (Jan 2021 - Aug 2022)\\n- Director @ Morgan Stanley (Jan 2018 - Jan 2021)\\n- Manager @ Morgan Stanley (Sep 2016 - Jan 2018)\\n- Lead Business Analyst @ Moody's Investors Service (Oct 2015 - Sep 2016)\\n- Business Analyst @ Moody's Investors Service (Aug 2014 - Sep 2015)\\n\\nEducation:\\n- NEOMA Business School (Advanced Master - Finance)\\n- Troyes Business School (Master of Management - Market Finance)\\n- Massey University (Economics - Management)\\n- IFCE (3-year undergraduate studies in Accounting to prepare for the French Chartered Accountant Certif. - Management)\\n\\nCertifications:\\n- Learning Python (LinkedIn Learning ⋅ Course Certificate)\\n- AMF Certification (French Financial Markets Supervisor) (CFPB)\\n- FactSet (Factset)\\n- FX Essentials (Bloomberg Essentials Training Program)\\n- Equity Essentials (Bloomberg Essentials Training Program)\\n- Commodity Essentials (Bloomberg Essentials Training Program)\\n\\nTop Skills:\\nFinancial Analysis, Financial Reporting, Risk Analysis, Accounting, Corporate Finance, Corporate Valuation, Finance, Market Data, Business Process Design, Ethics, Fixed Income, Derivatives, Reuters 3000, FactSet, Valuation"
    },
    {
        "first_name": "Susan",
        "last_name": "Patel",
        "headline": "287 Million in our Database HIGH INTENT Tracking 87 Billion Online Behaviors Weekly",
        "org_name": "Strategic Digital Tech",
        "location": "Greater Chicago Area",
        "linkedin": "https://www.linkedin.com/in/susan-patel-1b03264",
        "amount": 6500.0,
        "title": "Susan Patel - Strategic Digital Tech - Lead Generation Executive",
        "summary": "I specialize in results-driven lead generation with a Tri-Fecta Lead Generation Program designed to connect businesses of all sizes with ready-to-buy customers—guaranteed. If we don’t deliver intent-driven buyers, you don’t pay.\n\nWith 20+ years of ex",
        "notes": "LinkedIn Profile: https://www.linkedin.com/in/susan-patel-1b03264\\nHeadline: 287 Million in our Database HIGH INTENT Tracking 87 Billion Online Behaviors Weekly\\nConnections: 4821 | Followers: 4812\\nLocation: Greater Chicago Area\\n\\nAbout:\\nI specialize in results-driven lead generation with a Tri-Fecta Lead Generation Program designed to connect businesses of all sizes with ready-to-buy customers—guaranteed. If we don’t deliver intent-driven buyers, you don’t pay.\n\nWith 20+ years of experience in business development, networking, and strategic growth, I help companies replace cold calls with warm conversations. My approach focuses on quality over quantity, ensuring you get leads that convert.\n\n🚀 What I Offer:\n✅ Tri-Fecta Lead Generation Program – Proven, pay-for-results system\n✅ Customized Digital & Offline Strategies – Tailored for your industry\n✅ Exclusive Networking & Business Growth Insights – Helping you scale\n✅ No-Risk, High-Reward – You only pay for real results\n\nLet’s talk about how we can drive your business forward with high-intent leads. Connect with me today!\n\n#LeadGeneration #BusinessGrowth #Marketing #Networking #SalesSuccess\\n\\nExperience:\\n- Lead Generation Executive @ Strategic Digital Tech (Jun 2021 - Present)\\n- Regional Director of Business Development @ Strategic Digital Tech (Jan 2020 - Present)\\n- Director of Recruiting @ Strategic Solutions, LLC. (May 2007 - May 2015)\\n- Vice President of Business Development @ DSS Consulting (2004 - 2007)\\n- District Sales Manager @ Labor Ready (1995 - 1998)\\n\\nEducation:\\n- Triton (Diploma - Business)\\n- Oral Roberts University\\n- billye brim bible institute (Christian Studies - )\\n\\nTop Skills:\\nApplicant Tracking Systems, Salesforce.com, Management, Leadership, Strategic Planning, Team Leadership, Negotiation, Strategy, ⭐ PROJECT #1 — Buyer-Intent Lead Generation System, B2C Marketing, B2C, Online Marketing, Online Advertising, Digital Marketing, Digital Media"
    }
];


$insertedCount = 0;
foreach ($leadsPayload as $lp) {
    // Check if person already exists by first and last name
    $existingPerson = Person::where('first_name', $lp['first_name'])
        ->where('last_name', $lp['last_name'])
        ->first();
        
    if ($existingPerson) {
        echo "Skipping existing lead: {$lp['first_name']} {$lp['last_name']}\n";
        continue;
    }

    // 1. Organization
    $org = Organization::firstOrCreate(
        ['name' => $lp['org_name']],
        [
            'external_id' => Str::uuid()->toString(),
            'user_owner_id' => $userId,
        ]
    );

    // 2. Person
    $person = Person::create([
        'external_id' => Str::uuid()->toString(),
        'first_name' => $lp['first_name'],
        'last_name' => $lp['last_name'],
        'job_title' => $lp['headline'],
        'organization_id' => $org->id,
        'user_owner_id' => $userId,
    ]);

    // 3. Address
    if ($addressType) {
        Address::create([
            'external_id' => Str::uuid()->toString(),
            'address_type_id' => $addressType->id,
            'address' => $lp['location'],
            'primary' => true,
            'addressable_type' => Person::class,
            'addressable_id' => $person->id,
        ]);
    }

    // 4. Lead
    $num = $leadIdx++;
    $lead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'lead_id' => 'TFA-L' . $num,
        'prefix' => 'TFA-L',
        'number' => $num,
        'title' => $lp['title'],
        'description' => $lp['summary'],
        'amount' => $lp['amount'],
        'currency' => 'USD',
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'lead_source_id' => $leadSource->id,
        'pipeline_id' => $leadPipeline->id,
        'pipeline_stage_id' => $leadStageNew->id,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
        'user_assigned_id' => $userId,
    ]);

    if ($labelQuant) {
        $lead->labels()->attach($labelQuant->id);
    }

    // 5. Detailed Background Note
    Note::create([
        'external_id' => Str::uuid()->toString(),
        'content' => $lp['notes'],
        'noteable_type' => Lead::class,
        'noteable_id' => $lead->id,
        'user_created_id' => $userId,
        'user_updated_id' => $userId,
    ]);

    $insertedCount++;
    echo "Created Lead TFA-L{$num}: {$lp['title']}\n";
}

echo "Done! Successfully inserted {$insertedCount} new leads into the database.\n";
