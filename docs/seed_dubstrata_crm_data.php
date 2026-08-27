<?php

require __DIR__ . '/../vendor/autoload.php';

// Set production DB env before bootstrapping Testbench
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

$app = require_once __DIR__ . '/../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$app->register(\VentureDrake\LaravelCrm\LaravelCrmServiceProvider::class);
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Ensure CRM table prefix config is set
config(['laravel-crm.db_table_prefix' => 'crm_']);

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Address;
use VentureDrake\LaravelCrm\Models\AddressType;
use VentureDrake\LaravelCrm\Models\Call;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\DealProduct;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\DeliveryProduct;
use VentureDrake\LaravelCrm\Models\Email;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\InvoiceLine;
use VentureDrake\LaravelCrm\Models\Label;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\Lunch;
use VentureDrake\LaravelCrm\Models\Meeting;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\OrderProduct;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Phone;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\ProductCategory;
use VentureDrake\LaravelCrm\Models\ProductPrice;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;
use VentureDrake\LaravelCrm\Models\PurchaseOrderLine;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\QuoteProduct;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Models\TaxRate;

echo "===============================================================\n";
echo "  DUBSTRATA ENTERPRISE CRM — DATABASE RESET & SEED SCRIPT     \n";
echo "===============================================================\n\n";

// 1. Resolve primary user
$userModel = app(config('auth.providers.users.model', 'App\Models\User'));
$user = $userModel::first();
if (! $user) {
    echo "ERROR: No primary user found in host database.\n";
    exit(1);
}
$userId = $user->id;
echo "-> Operating as User ID: {$userId} ({$user->name} <{$user->email}>)\n";

// Ensure CRM access
if (! $user->crm_access) {
    $user->forceFill(['crm_access' => true])->save();
}

// 2. Disable Foreign Keys & Truncate Entity Tables
echo "-> Truncating old templated/fake CRM items...\n";
Schema::disableForeignKeyConstraints();

$prefix = config('laravel-crm.db_table_prefix', 'crm_');

$tablesToTruncate = [
    'activities', 'notes', 'tasks', 'calls', 'meetings', 'lunches', 'files',
    'delivery_products', 'deliveries',
    'purchase_order_lines', 'purchase_orders',
    'invoice_lines', 'invoices',
    'order_products', 'orders',
    'quote_products', 'quotes',
    'deal_products', 'deals',
    'leads',
    'contacts', 'phones', 'emails', 'addresses',
    'people', 'organizations',
    'product_prices', 'products', 'product_categories',
    'lead_sources', 'labels', 'labelables',
    'chat_messages', 'chat_conversations', 'chat_visitor_page_views', 'chat_visitors', 'chat_widgets',
    'email_campaign_recipients', 'email_campaign_clicks', 'email_campaigns', 'email_templates',
    'sms_campaign_recipients', 'sms_campaign_clicks', 'sms_campaigns', 'sms_templates',
    'monitor_checks', 'monitors',
    'feature_comments', 'feature_votes', 'feature_views', 'features', 'feature_statuses',
    'field_values', 'field_models', 'field_options', 'fields', 'field_groups',
    'user_invitations',
];

foreach ($tablesToTruncate as $table) {
    $full = $prefix . $table;
    if (Schema::hasTable($full)) {
        DB::table($full)->truncate();
    }
}

Schema::enableForeignKeyConstraints();
echo "-> All old templated records successfully purged!\n\n";

// 3. Ensure Default Tax Rate (14% VAT for Techfusion Automata / Dubstrata SA)
$taxRate = TaxRate::firstOrCreate(
    ['name' => 'VAT 14%'],
    ['external_id' => Str::uuid()->toString(), 'description' => 'South African Standard Value-Added Tax (14%)', 'rate' => 14, 'default' => true]
);
echo "-> Tax Rate: VAT 14% configured.\n";

// 4. Seed Dubstrata Lead Sources
echo "-> Seeding Dubstrata Lead Sources...\n";
$sources = [
    'Institutional Direct Outreach',
    'Quant & Systematic Trading Conferences',
    'Polymarket & Web3 Ecosystem Partners',
    'Model Context Protocol (FastMCP) Hub',
    'Inbound Technical Pilot Request',
    'Hedge Fund CTO Network Referral',
];
$leadSourceModels = [];
foreach ($sources as $srcName) {
    $leadSourceModels[$srcName] = LeadSource::create([
        'name' => $srcName,
        'external_id' => Str::uuid()->toString(),
    ]);
}

// 5. Seed Dubstrata Labels
echo "-> Seeding Dubstrata Labels...\n";
$labelsData = [
    'Enterprise Pilot' => '00E5FF',
    'Quant Desk' => '38BDF8',
    'HFT / Algo Swarm' => '818CF8',
    'Web3 Native' => 'A855F7',
    'High Priority' => 'EF4444',
    'MNPI & Compliance Clear' => '10B981',
    'VPC Peering Required' => 'F59E0B',
    'x402 Micro-Metered' => 'EC4899',
];
$labelModels = [];
foreach ($labelsData as $lbl => $hex) {
    $labelModels[$lbl] = Label::create([
        'name' => $lbl,
        'hex' => $hex,
        'external_id' => Str::uuid()->toString(),
    ]);
}

// 6. Seed Dubstrata Products & Categories
echo "-> Seeding Dubstrata Enterprise Data Products & Services...\n";

$catData = [
    'Enterprise Data Subscriptions' => [
        [
            'name' => 'Tier 1: Streaming Signals (WebSocket & Kafka)',
            'code' => 'DUB-SIG-T1',
            'price' => 3500.00,
            'cost' => 500.00,
            'desc' => 'Real-time mispricing alerts, Clean HHI shifts, top-of-book CLOB microstructure feeds, FinVIC metrics stream.',
        ],
        [
            'name' => 'Tier 2: Production Terminal (Dashboard + Signals)',
            'code' => 'DUB-TRM-T2',
            'price' => 6500.00,
            'cost' => 900.00,
            'desc' => 'Live Command Center Web Dashboard, 2D/3D causal graph explorer, 10-section AI Intelligence Report synthesis, rumor referee auditor.',
        ],
        [
            'name' => 'Tier 3: Enterprise Full Graph & Ledger Access',
            'code' => 'DUB-GRP-T3',
            'price' => 12500.00,
            'cost' => 2000.00,
            'desc' => 'Direct Cypher/SQL query access to ArcadeDB, Flink SQL Materialized Table, Apache Parquet GCS data lake sync, historical backtester API.',
        ],
    ],
    'Infrastructure & Hosting Addons' => [
        [
            'name' => 'Dedicated VPC Peering & Transit Gateway',
            'code' => 'DUB-INF-VPC',
            'price' => 2500.00,
            'cost' => 400.00,
            'desc' => 'Direct private network interconnect via AWS Transit Gateway or GCP Cloud Interconnect.',
        ],
        [
            'name' => 'Tenant-Isolated Private Graph Partition',
            'code' => 'DUB-INF-PRV',
            'price' => 2500.00,
            'cost' => 350.00,
            'desc' => 'Dedicated private causal graph partition for internal research and private deal memos.',
        ],
        [
            'name' => 'On-Premise Graph Appliance Kubernetes Package',
            'code' => 'DUB-INF-K8S',
            'price' => 15000.00,
            'cost' => 3000.00,
            'desc' => 'Containerized Docker/Kubernetes deployment package for air-gapped sovereign networks.',
        ],
    ],
    'Micro-Metered API Credit Packs (x402 Protocol)' => [
        [
            'name' => 'Enterprise Micro-Metered API Credit Pack (100k Credits)',
            'code' => 'DUB-X402-100K',
            'price' => 1000.00,
            'cost' => 100.00,
            'desc' => '100,000 API Credits for Cypher RAG context queries ($0.005), intelligence reports ($0.020), and forward models ($0.015).',
        ],
        [
            'name' => 'Institutional Micro-Metered API Credit Pack (500k Credits)',
            'code' => 'DUB-X402-500K',
            'price' => 4500.00,
            'cost' => 450.00,
            'desc' => '500,000 API Credits for high-frequency quantitative swarms and automated prediction market arbitrage.',
        ],
    ],
    'Quantitative Modeling & Custom Ingestion' => [
        [
            'name' => 'FinVIC Multi-Factor Backtesting Suite License',
            'code' => 'DUB-QNT-VIC',
            'price' => 5000.00,
            'cost' => 600.00,
            'desc' => 'Multi-factor backtesting suite with fractional Kelly sizing and historical temporal graph locking.',
        ],
        [
            'name' => 'Custom Crawler Target & Regulatory Wires Ingestion',
            'code' => 'DUB-QNT-ING',
            'price' => 3500.00,
            'cost' => 500.00,
            'desc' => 'Custom ingestion setup for proprietary news wires, SEC EDGAR filings, or sovereign data streams.',
        ],
    ],
];

$productModels = [];
foreach ($catData as $catName => $products) {
    $category = ProductCategory::create([
        'name' => $catName,
        'external_id' => Str::uuid()->toString(),
    ]);

    foreach ($products as $pData) {
        $prod = Product::create([
            'name' => $pData['name'],
            'code' => $pData['code'],
            'description' => $pData['desc'],
            'product_category_id' => $category->id,
            'tax_rate_id' => $taxRate->id,
            'active' => true,
            'user_created_id' => $userId,
            'user_owner_id' => $userId,
            'external_id' => Str::uuid()->toString(),
        ]);

        ProductPrice::create([
            'external_id' => Str::uuid()->toString(),
            'product_id' => $prod->id,
            'unit_price' => $pData['price'],
            'cost_per_unit' => $pData['cost'],
            'currency' => 'USD',
        ]);

        $productModels[$pData['code']] = $prod;
    }
}

// 7. Seed Institutional Organizations & Key Contacts (People)
echo "-> Seeding Institutional Organizations & Key Contacts...\n";

$currentTypeId = AddressType::where('name', 'Current')->first()->id ?? 1;
$businessTypeId = AddressType::where('name', 'Business')->first()->id ?? 4;

$orgsData = [
    [
        'name' => 'Millennium Management LLC',
        'desc' => 'Multi-strategy global quantitative hedge fund managing systematic alpha portfolios.',
        'city' => 'New York', 'state' => 'NY', 'country' => 'United States', 'code' => '10022',
        'address' => '399 Park Avenue, 10th Floor',
        'email' => 'info@mlp.com', 'phone' => '+1 212 841 4000',
        'contact' => [
            'title' => 'Dr.', 'first' => 'Aris', 'last' => 'Thorne',
            'desc' => 'Head of Quantitative Alternative Data Procurement',
            'email' => 'a.thorne@mlp.com', 'phone' => '+1 212 841 4088',
        ],
    ],
    [
        'name' => 'Point72 Asset Management',
        'desc' => 'Institutional asset manager leveraging systematic models and alternative data feeds.',
        'city' => 'Stamford', 'state' => 'CT', 'country' => 'United States', 'code' => '06902',
        'address' => '72 Cummings Point Road',
        'email' => 'contact@point72.com', 'phone' => '+1 203 890 2000',
        'contact' => [
            'title' => 'Ms.', 'first' => 'Elena', 'last' => 'Rostova',
            'desc' => 'Lead Systematic Portfolio Manager',
            'email' => 'elena.rostova@point72.com', 'phone' => '+1 203 890 2145',
        ],
    ],
    [
        'name' => 'Jane Street Capital',
        'desc' => 'Quantitative trading firm and global liquidity provider across traditional & crypto markets.',
        'city' => 'New York', 'state' => 'NY', 'country' => 'United States', 'code' => '10281',
        'address' => '250 Vesey Street, 3rd Floor',
        'email' => 'quant@janestreet.com', 'phone' => '+1 212 651 6000',
        'contact' => [
            'title' => 'Mr.', 'first' => 'Marcus', 'last' => 'Vance',
            'desc' => 'Head of AI Trading Swarms & Algorithmic Strategy',
            'email' => 'm.vance@janestreet.com', 'phone' => '+1 212 651 6120',
        ],
    ],
    [
        'name' => 'Citadel Securities',
        'desc' => 'Leading global market maker servicing institutional orderbook depth and quantitative execution.',
        'city' => 'Chicago', 'state' => 'IL', 'country' => 'United States', 'code' => '60603',
        'address' => '131 South Dearborn Street',
        'email' => 'info@citadelsecurities.com', 'phone' => '+1 312 395 2000',
        'contact' => [
            'title' => 'Ms.', 'first' => 'Sarah', 'last' => 'Lin',
            'desc' => 'VP Quantitative Microstructure Research',
            'email' => 'sarah.lin@citadelsecurities.com', 'phone' => '+1 312 395 2450',
        ],
    ],
    [
        'name' => 'Two Sigma Investments',
        'desc' => 'Systematic investment manager using AI, machine learning, and high-dimensional data science.',
        'city' => 'New York', 'state' => 'NY', 'country' => 'United States', 'code' => '10013',
        'address' => '100 Avenue of the Americas',
        'email' => 'contact@twosigma.com', 'phone' => '+1 212 625 5700',
        'contact' => [
            'title' => 'Dr.', 'first' => 'Henrik', 'last' => 'Lindqvist',
            'desc' => 'Chief Data Officer & Causal ML Director',
            'email' => 'h.lindqvist@twosigma.com', 'phone' => '+1 212 625 5810',
        ],
    ],
    [
        'name' => 'Balyasny Asset Management (BAM)',
        'desc' => 'Global multi-strategy investment firm focused on systematic macro and alternative alpha.',
        'city' => 'Chicago', 'state' => 'IL', 'country' => 'United States', 'code' => '60606',
        'address' => '440 South LaSalle Street, Suite 3300',
        'email' => 'info@bamfunds.com', 'phone' => '+1 312 499 2000',
        'contact' => [
            'title' => 'Mr.', 'first' => 'Julian', 'last' => 'Vance-Webb',
            'desc' => 'Senior Portfolio Manager, Macro Catalyst Signals',
            'email' => 'j.vance-webb@bamfunds.com', 'phone' => '+1 312 499 2190',
        ],
    ],
    [
        'name' => 'Jump Trading Group',
        'desc' => 'Research-driven quantitative trading firm specializing in high-frequency crypto & CLOB microstructure.',
        'city' => 'Chicago', 'state' => 'IL', 'country' => 'United States', 'code' => '60654',
        'address' => '600 West Chicago Avenue, Suite 600',
        'email' => 'info@jumptrading.com', 'phone' => '+1 312 758 4000',
        'contact' => [
            'title' => 'Mr.', 'first' => 'Taro', 'last' => 'Takahashi',
            'desc' => 'Head of Crypto Arbitrage & CLOB Infrastructure',
            'email' => 'ttakahashi@jumptrading.com', 'phone' => '+1 312 758 4300',
        ],
    ],
    [
        'name' => 'Qube Research & Technologies (QRT)',
        'desc' => 'Global quantitative investment manager operating proprietary systematic algorithms.',
        'city' => 'London', 'state' => 'England', 'country' => 'United Kingdom', 'code' => 'W1J 6ER',
        'address' => '11 Berkeley Street, Mayfair',
        'email' => 'contact@qube-rt.com', 'phone' => '+44 20 7070 7000',
        'contact' => [
            'title' => 'Ms.', 'first' => 'Claire', 'last' => 'Dubois',
            'desc' => 'Lead Quant Analyst, Event-Driven Alpha',
            'email' => 'clara.dubois@qube-rt.com', 'phone' => '+44 20 7070 7150',
        ],
    ],
    [
        'name' => 'Wintermute Trading',
        'desc' => 'Leading algorithmic Web3 market maker and prediction market liquidity provider.',
        'city' => 'London', 'state' => 'England', 'country' => 'United Kingdom', 'code' => 'EC2A 4NE',
        'address' => '100 Bishopsgate, 18th Floor',
        'email' => 'info@wintermute.com', 'phone' => '+44 20 3808 1000',
        'contact' => [
            'title' => 'Mr.', 'first' => 'Vikram', 'last' => 'Patel',
            'desc' => 'Head of Automated Market Making',
            'email' => 'vikram@wintermute.com', 'phone' => '+44 20 3808 1090',
        ],
    ],
    [
        'name' => 'Hudson River Trading (HRT)',
        'desc' => 'Quantitative trading company bringing automated technology to financial markets.',
        'city' => 'New York', 'state' => 'NY', 'country' => 'United States', 'code' => '10007',
        'address' => '4 World Trade Center, 57th Floor',
        'email' => 'info@hudson-trading.com', 'phone' => '+1 212 293 1400',
        'contact' => [
            'title' => 'Mr.', 'first' => 'Alexander', 'last' => 'Wright',
            'desc' => 'Partner & Head of Infrastructure Engineering',
            'email' => 'a.wright@hudson-trading.com', 'phone' => '+1 212 293 1455',
        ],
    ],
];

$orgModels = [];
$personModels = [];

foreach ($orgsData as $oData) {
    $org = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => $oData['name'],
        'description' => $oData['desc'],
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);

    Email::create([
        'external_id' => Str::uuid()->toString(),
        'address' => $oData['email'],
        'type' => 'work',
        'primary' => true,
        'emailable_type' => Organization::class,
        'emailable_id' => $org->id,
    ]);

    Phone::create([
        'external_id' => Str::uuid()->toString(),
        'number' => $oData['phone'],
        'type' => 'work',
        'primary' => true,
        'phoneable_type' => Organization::class,
        'phoneable_id' => $org->id,
    ]);

    Address::create([
        'external_id' => Str::uuid()->toString(),
        'address_type_id' => $businessTypeId,
        'line1' => $oData['address'],
        'city' => $oData['city'],
        'state' => $oData['state'],
        'code' => $oData['code'],
        'country' => $oData['country'],
        'primary' => true,
        'addressable_type' => Organization::class,
        'addressable_id' => $org->id,
    ]);

    $orgModels[$oData['name']] = $org;

    // Contact Person
    $c = $oData['contact'];
    $person = Person::create([
        'external_id' => Str::uuid()->toString(),
        'title' => $c['title'],
        'first_name' => $c['first'],
        'last_name' => $c['last'],
        'organization_id' => $org->id,
        'description' => $c['desc'] . ' at ' . $org->name,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);

    Email::create([
        'external_id' => Str::uuid()->toString(),
        'address' => $c['email'],
        'type' => 'work',
        'primary' => true,
        'subscribed' => true,
        'emailable_type' => Person::class,
        'emailable_id' => $person->id,
    ]);

    Phone::create([
        'external_id' => Str::uuid()->toString(),
        'number' => $c['phone'],
        'type' => 'work',
        'primary' => true,
        'subscribed' => true,
        'phoneable_type' => Person::class,
        'phoneable_id' => $person->id,
    ]);

    Address::create([
        'external_id' => Str::uuid()->toString(),
        'address_type_id' => $currentTypeId,
        'line1' => $oData['address'],
        'city' => $oData['city'],
        'state' => $oData['state'],
        'code' => $oData['code'],
        'country' => $oData['country'],
        'primary' => true,
        'addressable_type' => Person::class,
        'addressable_id' => $person->id,
    ]);

    $personModels[$oData['name']] = $person;
}

// 8. Pipeline Resolution
$leadPipeline = Pipeline::where('model', Lead::class)->first() ?? Pipeline::first();
$leadStages = PipelineStage::where('pipeline_id', $leadPipeline->id)->get();
$leadStageNew = $leadStages->firstWhere('name', 'New') ?? $leadStages->first();
$leadStageAppt = $leadStages->firstWhere('name', 'Appointment Scheduled') ?? $leadStages->skip(1)->first();
$leadStageQual = $leadStages->firstWhere('name', 'Qualified To Buy') ?? $leadStages->skip(2)->first();
$leadStageWon = $leadStages->firstWhere('name', 'Closed Won') ?? $leadStages->last();

$dealPipeline = Pipeline::where('model', Deal::class)->first() ?? Pipeline::skip(1)->first();
$dealStages = PipelineStage::where('pipeline_id', $dealPipeline->id)->get();
$dealStageDraft = $dealStages->firstWhere('name', 'Draft') ?? $dealStages->first();
$dealStageQual = $dealStages->firstWhere('name', 'Qualified') ?? $dealStages->first();
$dealStageProp = $dealStages->firstWhere('name', 'Proposal Sent') ?? $dealStages->skip(2)->first();
$dealStageNeg = $dealStages->firstWhere('name', 'Negotiation') ?? $dealStages->skip(3)->first();
$dealStagePend = $dealStages->firstWhere('name', 'Pending') ?? $dealStages->skip(4)->first();
$dealStageWon = $dealStages->firstWhere('name', 'Closed Won') ?? $dealStages->last();

$quotePipeline = Pipeline::where('model', Quote::class)->first() ?? Pipeline::skip(2)->first();
$quoteStages = PipelineStage::where('pipeline_id', $quotePipeline->id)->get();
$quoteStageDraft = $quoteStages->firstWhere('name', 'Draft') ?? $quoteStages->first();
$quoteStageSent = $quoteStages->firstWhere('name', 'Sent') ?? $quoteStages->skip(1)->first();
$quoteStageAcc = $quoteStages->firstWhere('name', 'Accepted') ?? $quoteStages->skip(2)->first();

$orderPipeline = Pipeline::where('model', Order::class)->first() ?? Pipeline::skip(3)->first();
$orderStages = PipelineStage::where('pipeline_id', $orderPipeline->id)->get();
$orderStageOpen = $orderStages->firstWhere('name', 'Open') ?? $orderStages->skip(1)->first();
$orderStageComp = $orderStages->firstWhere('name', 'Completed') ?? $orderStages->last();

// 9. Seed Leads
echo "-> Seeding Dubstrata Leads...\n";
$leadsData = [
    [
        'org' => 'Millennium Management LLC',
        'title' => 'Millennium Management - Alt Data Causal Graph Evaluation',
        'desc' => 'Evaluation of ArcadeDB 3072-dim vector property graph & dynamic directional causal predicates for Systematic Macro portfolio.',
        'amount' => 12500.00,
        'source' => 'Quant & Systematic Trading Conferences',
        'stage' => $leadStageNew->id,
        'label' => 'Enterprise Pilot',
    ],
    [
        'org' => 'Jane Street Capital',
        'title' => 'Jane Street - Polymarket CLOB Microstructure WebSocket Stream',
        'desc' => 'Real-time WebSocket alerts feed for phantom orderbook spoofing ($50k/3s) and Clean HHI sybil wallet detection.',
        'amount' => 3500.00,
        'source' => 'Model Context Protocol (FastMCP) Hub',
        'stage' => $leadStageAppt->id,
        'label' => 'Quant Desk',
    ],
    [
        'org' => 'Two Sigma Investments',
        'title' => 'Two Sigma - 3072-dim LSM Vector Cypher RAG Integration',
        'desc' => 'Direct Cypher RAG context query integration via @dubstrata/mcp-server for autonomous trading swarms.',
        'amount' => 6500.00,
        'source' => 'Institutional Direct Outreach',
        'stage' => $leadStageQual->id,
        'label' => 'High Priority',
    ],
    [
        'org' => 'Wintermute Trading',
        'title' => 'Wintermute - Real-time CSI Sentiment & Sybil Cluster Detection',
        'desc' => 'Automated Web3 prediction market arbitrage signals & x402 payment protocol micro-metered execution.',
        'amount' => 4500.00,
        'source' => 'Polymarket & Web3 Ecosystem Partners',
        'stage' => $leadStageWon->id,
        'label' => 'Web3 Native',
        'converted' => true,
    ],
    [
        'org' => 'Balyasny Asset Management (BAM)',
        'title' => 'Balyasny - Event-Driven Commodity & Sovereign Debt Causal Signals',
        'desc' => 'Causal narrative graph tracking Brent Crude, TNX, and SEC EDGAR regulatory decisions for systematic risk desks.',
        'amount' => 12500.00,
        'source' => 'Hedge Fund CTO Network Referral',
        'stage' => $leadStageAppt->id,
        'label' => 'VPC Peering Required',
    ],
];

$leadModels = [];
$leadIdx = 1001;
foreach ($leadsData as $ld) {
    $org = $orgModels[$ld['org']];
    $person = $personModels[$ld['org']];

    $lead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'lead_id' => 'TFA-L' . $leadIdx,
        'prefix' => 'TFA-L',
        'number' => $leadIdx++,
        'title' => $ld['title'],
        'description' => $ld['desc'],
        'amount' => $ld['amount'],
        'currency' => 'USD',
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'lead_source_id' => $leadSourceModels[$ld['source']]->id ?? null,
        'pipeline_id' => $leadPipeline->id,
        'pipeline_stage_id' => $ld['stage'],
        'converted_at' => isset($ld['converted']) ? Carbon::now()->subDays(10) : null,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
        'user_assigned_id' => $userId,
    ]);

    if (isset($labelModels[$ld['label']])) {
        $lead->labels()->attach($labelModels[$ld['label']]->id);
    }

    $leadModels[$ld['org']] = $lead;
}

// 10. Seed Deals & Deal Line Items
echo "-> Seeding Dubstrata Deals & Quantitative Contracts...\n";

$dealsData = [
    [
        'org' => 'Point72 Asset Management',
        'title' => 'Point72 - Enterprise Full Graph & Parquet Data Lake Sync (Tier 3)',
        'desc' => 'Direct ArcadeDB Cypher access, Flink SQL Materialized Table, and daily GCS Parquet data lake sync with zero lookahead bias.',
        'amount' => 17500.00,
        'stage' => $dealStageNeg->id,
        'prob' => 70,
        'products' => [
            ['code' => 'DUB-GRP-T3', 'qty' => 1, 'price' => 12500.00],
            ['code' => 'DUB-INF-VPC', 'qty' => 1, 'price' => 2500.00],
            ['code' => 'DUB-INF-PRV', 'qty' => 1, 'price' => 2500.00],
        ],
        'labels' => ['Enterprise Pilot', 'MNPI & Compliance Clear', 'VPC Peering Required'],
    ],
    [
        'org' => 'Jump Trading Group',
        'title' => 'Jump Trading - High-Frequency WebSocket Signal Feed (Tier 1 + VPC)',
        'desc' => 'Sub-50ms WebSocket signal feed (`/api/v1/ws/alerts`) with top-of-book depth dynamics and dedicated AWS Transit Gateway VPC.',
        'amount' => 6000.00,
        'stage' => $dealStageWon->id,
        'prob' => 100,
        'status' => 'won',
        'products' => [
            ['code' => 'DUB-SIG-T1', 'qty' => 1, 'price' => 3500.00],
            ['code' => 'DUB-INF-VPC', 'qty' => 1, 'price' => 2500.00],
        ],
        'labels' => ['HFT / Algo Swarm', 'VPC Peering Required'],
    ],
    [
        'org' => 'Qube Research & Technologies (QRT)',
        'title' => 'Qube Research - Production Terminal & 10-Section Intelligence Briefings (Tier 2)',
        'desc' => 'Interactive 2D/3D visual causal graph command center with 5 analyst user seats and automated conflict refereeing.',
        'amount' => 7500.00,
        'stage' => $dealStageProp->id,
        'prob' => 50,
        'products' => [
            ['code' => 'DUB-TRM-T2', 'qty' => 1, 'price' => 6500.00],
            ['code' => 'DUB-X402-100K', 'qty' => 1, 'price' => 1000.00],
        ],
        'labels' => ['Quant Desk', 'High Priority'],
    ],
    [
        'org' => 'Hudson River Trading (HRT)',
        'title' => 'Hudson River Trading - On-Premise Air-Gapped Graph Appliance & Custom Ingestion',
        'desc' => 'Containerized Kubernetes ArcadeDB property graph appliance for on-premise air-gapped sovereign risk deployment.',
        'amount' => 18500.00,
        'stage' => $dealStageQual->id,
        'prob' => 30,
        'products' => [
            ['code' => 'DUB-INF-K8S', 'qty' => 1, 'price' => 15000.00],
            ['code' => 'DUB-QNT-ING', 'qty' => 1, 'price' => 3500.00],
        ],
        'labels' => ['HFT / Algo Swarm', 'VPC Peering Required'],
    ],
    [
        'org' => 'Citadel Securities',
        'title' => 'Citadel Securities - Real-Time Microstructure Telemetry & Clean HHI Signals',
        'desc' => 'Orderbook depth dynamics, phantom spoofing alerts, and FinVIC multi-factor strategy backtesting suite.',
        'amount' => 8500.00,
        'stage' => $dealStagePend->id,
        'prob' => 90,
        'products' => [
            ['code' => 'DUB-SIG-T1', 'qty' => 1, 'price' => 3500.00],
            ['code' => 'DUB-QNT-VIC', 'qty' => 1, 'price' => 5000.00],
        ],
        'labels' => ['Quant Desk', 'MNPI & Compliance Clear'],
    ],
    [
        'org' => 'Wintermute Trading',
        'title' => 'Wintermute - x402 Protocol Algorithmic Micro-Payments & FastMCP Package',
        'desc' => '500,000 x402 API Credits denominated in USDC over Base EVM with FastMCP (@dubstrata/mcp-server) integration.',
        'amount' => 4500.00,
        'stage' => $dealStageWon->id,
        'prob' => 100,
        'status' => 'won',
        'products' => [
            ['code' => 'DUB-X402-500K', 'qty' => 1, 'price' => 4500.00],
        ],
        'labels' => ['Web3 Native', 'x402 Micro-Metered'],
    ],
];

$dealModels = [];
$dealIdx = 1001;

foreach ($dealsData as $dd) {
    $org = $orgModels[$dd['org']];
    $person = $personModels[$dd['org']];
    $lead = $leadModels[$dd['org']] ?? null;

    $deal = Deal::create([
        'external_id' => Str::uuid()->toString(),
        'deal_id' => 'TFA-D' . $dealIdx,
        'prefix' => 'TFA-D',
        'number' => $dealIdx++,
        'title' => $dd['title'],
        'description' => $dd['desc'],
        'amount' => $dd['amount'],
        'currency' => 'USD',
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'lead_id' => $lead ? $lead->id : null,
        'pipeline_id' => $dealPipeline->id,
        'pipeline_stage_id' => $dd['stage'],
        'expected_close' => Carbon::now()->addDays(mt_rand(15, 60)),
        'closed_status' => $dd['status'] ?? null,
        'closed_at' => isset($dd['status']) ? Carbon::now()->subDays(mt_rand(2, 8)) : null,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
        'user_assigned_id' => $userId,
    ]);

    foreach ($dd['products'] as $pItem) {
        $pModel = $productModels[$pItem['code']];
        DealProduct::create([
            'external_id' => Str::uuid()->toString(),
            'deal_id' => $deal->id,
            'product_id' => $pModel->id,
            'quantity' => $pItem['qty'],
            'price' => $pItem['price'],
            'amount' => $pItem['price'] * $pItem['qty'],
            'currency' => 'USD',
            'tax_rate' => 14,
        ]);
    }

    if (isset($dd['labels'])) {
        foreach ($dd['labels'] as $lbl) {
            if (isset($labelModels[$lbl])) {
                $deal->labels()->attach($labelModels[$lbl]->id);
            }
        }
    }

    $dealModels[$dd['org']] = $deal;
}

// 11. Seed Quotes & Quote Line Items
echo "-> Seeding Institutional Quotes & Commercial Proposals...\n";

$quotesData = [
    [
        'org' => 'Point72 Asset Management',
        'title' => 'Quote: Tier 3 Enterprise Full Graph & VPC Peering Partition',
        'ref' => 'REF-P72-2026',
        'deal' => 'Point72 Asset Management',
        'stage' => $quoteStageDraft->id,
        'items' => [
            ['code' => 'DUB-GRP-T3', 'qty' => 1, 'price' => 12500.00],
            ['code' => 'DUB-INF-VPC', 'qty' => 1, 'price' => 2500.00],
            ['code' => 'DUB-INF-PRV', 'qty' => 1, 'price' => 2500.00],
        ],
    ],
    [
        'org' => 'Qube Research & Technologies (QRT)',
        'title' => 'Quote: Tier 2 Production Terminal & 100k API Credit Pack',
        'ref' => 'REF-QRT-2026',
        'deal' => 'Qube Research & Technologies (QRT)',
        'stage' => $quoteStageSent->id,
        'items' => [
            ['code' => 'DUB-TRM-T2', 'qty' => 1, 'price' => 6500.00],
            ['code' => 'DUB-X402-100K', 'qty' => 1, 'price' => 1000.00],
        ],
    ],
    [
        'org' => 'Jump Trading Group',
        'title' => 'Quote: Tier 1 WebSocket Signal Stream & Transit Gateway VPC',
        'ref' => 'REF-JUMP-2026',
        'deal' => 'Jump Trading Group',
        'stage' => $quoteStageAcc->id,
        'accepted' => true,
        'items' => [
            ['code' => 'DUB-SIG-T1', 'qty' => 1, 'price' => 3500.00],
            ['code' => 'DUB-INF-VPC', 'qty' => 1, 'price' => 2500.00],
        ],
    ],
    [
        'org' => 'Wintermute Trading',
        'title' => 'Quote: Institutional 500k x402 API Credit Pack (USDC Base EVM)',
        'ref' => 'REF-WTR-2026',
        'deal' => 'Wintermute Trading',
        'stage' => $quoteStageAcc->id,
        'accepted' => true,
        'items' => [
            ['code' => 'DUB-X402-500K', 'qty' => 1, 'price' => 4500.00],
        ],
    ],
];

$quoteModels = [];
$quoteIdx = 1001;

foreach ($quotesData as $qd) {
    $org = $orgModels[$qd['org']];
    $person = $personModels[$qd['org']];
    $deal = $dealModels[$qd['deal']] ?? null;

    $subtotal = 0;
    foreach ($qd['items'] as $it) {
        $subtotal += $it['price'] * $it['qty'];
    }
    $tax = round($subtotal * 0.14, 2);
    $total = $subtotal + $tax;

    $quote = Quote::create([
        'external_id' => Str::uuid()->toString(),
        'quote_id' => 'TFA-Q' . $quoteIdx,
        'prefix' => 'TFA-Q',
        'number' => $quoteIdx++,
        'title' => $qd['title'],
        'description' => 'Dubstrata institutional proposal for ' . $org->name,
        'reference' => $qd['ref'],
        'deal_id' => $deal ? $deal->id : null,
        'lead_id' => $deal ? $deal->lead_id : null,
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'currency' => 'USD',
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total,
        'discount' => 0,
        'adjustments' => 0,
        'pipeline_id' => $quotePipeline->id,
        'pipeline_stage_id' => $qd['stage'],
        'issue_at' => Carbon::now()->subDays(5),
        'expire_at' => Carbon::now()->addDays(25),
        'accepted_at' => isset($qd['accepted']) ? Carbon::now()->subDays(2) : null,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
        'user_assigned_id' => $userId,
    ]);

    foreach ($qd['items'] as $it) {
        $pModel = $productModels[$it['code']];
        $itemAmt = $it['price'] * $it['qty'];
        QuoteProduct::create([
            'external_id' => Str::uuid()->toString(),
            'quote_id' => $quote->id,
            'product_id' => $pModel->id,
            'quantity' => $it['qty'],
            'price' => $it['price'],
            'amount' => $itemAmt,
            'currency' => 'USD',
            'tax_rate' => 14,
            'tax_amount' => round($itemAmt * 14), // whole cents
        ]);
    }

    $quoteModels[$qd['org']] = $quote;
}

// 12. Seed Executed Orders
echo "-> Seeding Executed Enterprise Orders...\n";

$ordersData = [
    [
        'org' => 'Jump Trading Group',
        'quote' => 'Jump Trading Group',
        'deal' => 'Jump Trading Group',
        'stage' => $orderStageOpen->id,
        'ref' => 'ORD-JUMP-9001',
    ],
    [
        'org' => 'Wintermute Trading',
        'quote' => 'Wintermute Trading',
        'deal' => 'Wintermute Trading',
        'stage' => $orderStageComp->id,
        'ref' => 'ORD-WTR-9002',
    ],
];

$orderModels = [];
$orderIdx = 1001;

foreach ($ordersData as $od) {
    $org = $orgModels[$od['org']];
    $person = $personModels[$od['org']];
    $quote = $quoteModels[$od['quote']];
    $deal = $dealModels[$od['deal']];

    $order = Order::create([
        'external_id' => Str::uuid()->toString(),
        'order_id' => 'TFA-O' . $orderIdx,
        'prefix' => 'TFA-O',
        'number' => $orderIdx++,
        'reference' => $od['ref'],
        'deal_id' => $deal->id,
        'quote_id' => $quote->id,
        'lead_id' => $deal->lead_id,
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'currency' => 'USD',
        'subtotal' => $quote->subtotal / 100,
        'tax' => $quote->tax / 100,
        'total' => $quote->total / 100,
        'discount' => 0,
        'adjustments' => 0,
        'pipeline_id' => $orderPipeline->id,
        'pipeline_stage_id' => $od['stage'],
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
        'user_assigned_id' => $userId,
    ]);

    foreach ($quote->quoteProducts as $qp) {
        $opAmt = $qp->amount / 100;
        OrderProduct::create([
            'external_id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'product_id' => $qp->product_id,
            'quote_product_id' => $qp->id,
            'quantity' => $qp->quantity,
            'price' => $qp->price / 100,
            'amount' => $opAmt,
            'currency' => 'USD',
            'tax_rate' => 14,
            'tax_amount' => round($opAmt * 14),
        ]);
    }

    $orderModels[$od['org']] = $order;
}

// 13. Seed Paid & Active Invoices
echo "-> Seeding Dubstrata Invoices...\n";

$invoicesData = [
    [
        'org' => 'Jump Trading Group',
        'order' => 'Jump Trading Group',
        'title' => 'Invoice: Tier 1 Streaming Signals & AWS Transit Gateway VPC',
        'paid' => true,
    ],
    [
        'org' => 'Wintermute Trading',
        'order' => 'Wintermute Trading',
        'title' => 'Invoice: Institutional 500k x402 API Credit Pack (Base USDC)',
        'paid' => true,
    ],
    [
        'org' => 'Point72 Asset Management',
        'order' => null,
        'title' => 'Invoice: Enterprise Tier 3 Causal Graph Pilot Downpayment Deposit',
        'paid' => false,
        'custom_amt' => 8750.00,
    ],
];

$invIdx = 1001;
foreach ($invoicesData as $invD) {
    $org = $orgModels[$invD['org']];
    $person = $personModels[$invD['org']];
    $order = isset($invD['order']) ? $orderModels[$invD['order']] : null;

    if ($order) {
        $subtotal = $order->subtotal / 100;
        $tax = $order->tax / 100;
        $total = $order->total / 100;
    } else {
        $subtotal = $invD['custom_amt'];
        $tax = round($subtotal * 0.14, 2);
        $total = $subtotal + $tax;
    }

    $invoice = Invoice::create([
        'external_id' => Str::uuid()->toString(),
        'invoice_id' => 'TFA-INV' . $invIdx,
        'prefix' => 'TFA-INV',
        'number' => $invIdx++,
        'title' => $invD['title'],
        'description' => 'Dubstrata institutional invoice for ' . $org->name,
        'order_id' => $order ? $order->id : null,
        'quote_id' => $order ? $order->quote_id : null,
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'currency' => 'USD',
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total,
        'discount' => 0,
        'adjustments' => 0,
        'issue_date' => Carbon::now()->subDays(7)->toDateString(),
        'due_date' => Carbon::now()->addDays(7)->toDateString(),
        'fully_paid_at' => $invD['paid'] ? Carbon::now()->subDays(2) : null,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);

    if ($order) {
        foreach ($order->orderProducts as $op) {
            $ipAmt = $op->amount / 100;
            InvoiceLine::create([
                'external_id' => Str::uuid()->toString(),
                'invoice_id' => $invoice->id,
                'product_id' => $op->product_id,
                'quantity' => $op->quantity,
                'price' => $op->price / 100,
                'amount' => $ipAmt,
                'currency' => 'USD',
                'tax_rate' => 14,
                'tax_amount' => round($ipAmt * 14),
            ]);
        }
    } else {
        InvoiceLine::create([
            'external_id' => Str::uuid()->toString(),
            'invoice_id' => $invoice->id,
            'product_id' => $productModels['DUB-GRP-T3']->id,
            'quantity' => 1,
            'price' => $subtotal,
            'amount' => $subtotal,
            'currency' => 'USD',
            'tax_rate' => 14,
            'tax_amount' => round($subtotal * 14),
        ]);
    }
}

// 14. Seed Deliveries & Technical Provisioning Notes
echo "-> Seeding Deliveries & Provisioning Clearances...\n";

$deliveriesData = [
    [
        'org' => 'Jump Trading Group',
        'order' => 'Jump Trading Group',
        'desc' => 'AWS Transit Gateway VPC Peering Interconnect & Single-Use Ephemeral WebSocket Redis Tickets Provisioned.',
    ],
    [
        'org' => 'Wintermute Trading',
        'order' => 'Wintermute Trading',
        'desc' => 'FastMCP SDK (@dubstrata/mcp-server) npm release package & Base EVM x402 Payment Protocol Authentication Keys Delivered.',
    ],
];

$delIdx = 1001;
foreach ($deliveriesData as $delD) {
    $org = $orgModels[$delD['org']];
    $person = $personModels[$delD['org']];
    $order = $orderModels[$delD['order']];

    $delivery = Delivery::create([
        'external_id' => Str::uuid()->toString(),
        'delivery_id' => 'TFA-DEL' . $delIdx,
        'prefix' => 'TFA-DEL',
        'number' => $delIdx++,
        'order_id' => $order->id,
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);

    foreach ($order->orderProducts as $op) {
        DeliveryProduct::create([
            'external_id' => Str::uuid()->toString(),
            'delivery_id' => $delivery->id,
            'product_id' => $op->product_id,
            'quantity' => $op->quantity,
        ]);
    }
}

// 15. Seed Infrastructure Purchase Orders
echo "-> Seeding Infrastructure Purchase Orders...\n";

$poData = [
    [
        'vendor' => 'ArcadeDB Enterprise Cloud Systems',
        'item' => 'ArcadeDB Cluster Compute Node Allocation (AWS Cape Town & Frankfurt)',
        'subtotal' => 4200.00,
    ],
    [
        'vendor' => 'Confluent Cloud Managed Services',
        'item' => 'Dedicated Kafka Cluster Topic Allocation (alt-data-time-series)',
        'subtotal' => 1800.00,
    ],
    [
        'vendor' => 'Google Cloud Armor WAF & Security Desk',
        'item' => 'High-frequency ingress WAF rate limiting & zkTLS RFC 3161 Timestamp Server',
        'subtotal' => 2500.00,
    ],
];

$poIdx = 1001;
foreach ($poData as $pod) {
    $tax = round($pod['subtotal'] * 0.14, 2);
    $total = $pod['subtotal'] + $tax;

    $po = PurchaseOrder::create([
        'external_id' => Str::uuid()->toString(),
        'purchase_order_id' => 'TFA-PO' . $poIdx,
        'prefix' => 'TFA-PO',
        'number' => $poIdx++,
        'subtotal' => $pod['subtotal'],
        'tax' => $tax,
        'total' => $total,
        'currency' => 'USD',
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);

    PurchaseOrderLine::create([
        'external_id' => Str::uuid()->toString(),
        'purchase_order_id' => $po->id,
        'product_id' => $productModels['DUB-INF-VPC']->id,
        'price' => $pod['subtotal'],
        'quantity' => 1,
        'amount' => $pod['subtotal'],
        'currency' => 'USD',
    ]);
}

// 16. Seed Activities, Tasks, Calls, Meetings, Lunches & Notes
echo "-> Seeding Tasks, Technical Calls, Meetings, Lunches & Notes...\n";

$tasksData = [
    [
        'title' => 'Verify RFC 3161 cryptographic timestamp validation on SEC EDGAR feed for Point72 POC',
        'desc' => 'Confirm SHA-256 signatures guarantee >90% verbatim character grounding for extracted claims.',
        'due' => Carbon::now()->addDays(1),
        'done' => false,
    ],
    [
        'title' => 'Configure Confluent Kafka dedicated topic alt-data-time-series for Jump Trading',
        'desc' => 'Map Flink SQL materialized tables alt_data_time_series_mt for sub-50ms streaming.',
        'due' => Carbon::now()->subDays(2),
        'done' => true,
    ],
    [
        'title' => 'Schedule MNPI compliance review with FSCA non-advisory legal counsel for Citadel Securities',
        'desc' => 'Establish zero Material Non-Public Information posture under FMA Act 19 of 2012.',
        'due' => Carbon::now()->addDays(4),
        'done' => false,
    ],
    [
        'title' => 'Review Clean HHI sybil cluster detection accuracy on Polymarket CLOB orderbooks',
        'desc' => 'Verify sub-wallet lineage merging for orders >= $50k cancelled in <= 3.0s.',
        'due' => Carbon::now()->subDays(1),
        'done' => true,
    ],
    [
        'title' => 'Issue 5 single-use ephemeral WebSocket tickets for Qube Research terminal evaluation',
        'desc' => 'Set Redis pre-flight auth tickets for POST /api/v1/auth/ws-ticket.',
        'due' => Carbon::now()->subDays(3),
        'done' => true,
    ],
];

foreach ($tasksData as $td) {
    Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => $td['title'],
        'description' => $td['desc'],
        'due_at' => $td['due'],
        'completed_at' => $td['done'] ? Carbon::now()->subDays(1) : null,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);
}

// Calls & Meetings
$callsData = [
    [
        'title' => 'VPC Peering & mTLS Architecture Review with HRT Engineering Lead Alexander Wright',
        'desc' => 'Reviewed containerized Kubernetes ArcadeDB property graph appliance for on-premise air-gapped sovereign risk deployment.',
    ],
    [
        'title' => 'Causal Graph Predicates & 3072-dim Vector Search Walkthrough with Dr. Aris Thorne',
        'desc' => 'Demonstrated multi-relational relational edges (EXPOSED_TO, THREATENS_SUPPLY_CHAIN_OF) over Millennium commodity tickers.',
    ],
    [
        'title' => 'Polymarket Phantom Spoofing ($50k / 3s) Alert Demo with Taro Takahashi',
        'desc' => 'Walked through top-of-book CLOB microstructure feeds and direction divergence signals (HYPE, SLEEPER).',
    ],
];

foreach ($callsData as $cd) {
    Call::create([
        'external_id' => Str::uuid()->toString(),
        'name' => $cd['title'],
        'description' => $cd['desc'],
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);
}

$meetingsData = [
    [
        'title' => 'FinVIC Multi-Factor & Fractional Kelly Sizing Backtest Review with Elena Rostova',
        'desc' => 'Evaluated risk-adjusted capital allocation recommendations (15%-80%) combining model win probability and causal relevance scores.',
        'start' => Carbon::now()->addDays(2)->setHour(14),
        'finish' => Carbon::now()->addDays(2)->setHour(15),
    ],
    [
        'title' => 'x402 Protocol Algorithmic Micropayments Architecture Session with Vikram Patel',
        'desc' => 'Configured USDC payment header verification over Solana & Base EVM for Wintermute trading swarms.',
        'start' => Carbon::now()->subDays(2)->setHour(11),
        'finish' => Carbon::now()->subDays(2)->setHour(12),
    ],
];

foreach ($meetingsData as $md) {
    Meeting::create([
        'external_id' => Str::uuid()->toString(),
        'name' => $md['title'],
        'description' => $md['desc'],
        'start_at' => $md['start'],
        'finish_at' => $md['finish'],
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);
}

$lunchesData = [
    [
        'title' => 'Quantitative Research Strategy Session with Dr. Henrik Lindqvist at Two Sigma',
        'desc' => 'Discussed ArcadeDB vector property graph scaling & zero future lookahead bias temporal locking.',
        'start' => Carbon::now()->addDays(3)->setHour(12),
        'finish' => Carbon::now()->addDays(3)->setHour(14),
    ],
];

foreach ($lunchesData as $ld) {
    Lunch::create([
        'external_id' => Str::uuid()->toString(),
        'name' => $ld['title'],
        'description' => $ld['desc'],
        'start_at' => $ld['start'],
        'finish_at' => $ld['finish'],
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);
}

// Notes attached to Organizations
$notesData = [
    [
        'org' => 'Point72 Asset Management',
        'content' => 'Point72 requires zero lookahead bias temporal locking (last_seen_at <= snapshot_ms) on all Cypher property graph queries for quantitative backtesting compliance.',
    ],
    [
        'org' => 'Wintermute Trading',
        'content' => 'Wintermute operates autonomous trading swarms executing Solana x402 payment protocol micro-payments for JIT vector search queries.',
    ],
    [
        'org' => 'Citadel Securities',
        'content' => 'Citadel Securities requested Clean HHI sybil filtering validation proof over 30-day historical Polymarket CLOB orderbook datasets.',
    ],
];

foreach ($notesData as $nd) {
    $org = $orgModels[$nd['org']];
    Note::create([
        'external_id' => Str::uuid()->toString(),
        'content' => $nd['content'],
        'notable_type' => Organization::class,
        'notable_id' => $org->id,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
    ]);
}

echo "\n===============================================================\n";
echo "  DUBSTRATA ENTERPRISE DATA RESET & SEEDING SUCCESSFULLY FINISHED! \n";
echo "===============================================================\n";
