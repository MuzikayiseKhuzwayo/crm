# How-To: Import Leads from Datasets & CSV

This guide explains how to import and deduplicate leads, contacts, and organizations from external datasets into Laravel CRM.

---

## 1. Importing from LinkedIn Profile Datasets

Laravel CRM ships with a dedicated importer for structured LinkedIn profile search datasets (JSON format).

### Command Signature
```bash
php artisan laravelcrm:import-linkedin-leads {path/to/dataset.json} [--user=1] [--dry-run]
```

### Options
- `{file.json}` (required): Absolute or relative path to the JSON dataset file.
- `--user=ID` (optional): Default CRM user ID to assign as the lead owner. Defaults to the first system user.
- `--dry-run` (optional): Simulates the import and outputs counts without writing to the database.

### What Gets Created
1. **Organization**: Deduplicated by company name.
2. **Person**: Deduplicated by first and last name; linked to the organization.
3. **Primary Address**: Parsed city, state, and country extracted from the location string.
4. **Lead**: Linked to the Person and Organization, assigned the source `LinkedIn`, placed in Stage 1 (`Cold Prospect` / `New`).
5. **Deduplication Check**: Profiles with existing matching LinkedIn profile URLs in `crm_leads.linkedin` are skipped automatically.

### Example Run
```bash
php artisan laravelcrm:import-linkedin-leads /var/data/linkedin_leads.json --user=1
```

---

## 2. Importing Contacts & People via Web UI

1. Navigate to **People** or **Organizations** in the sidebar.
2. Click the **Import** button in the header.
3. Download the CSV sample template.
4. Upload your populated CSV file.
5. Map any custom fields if prompted.
6. Click **Confirm Import**. The batch job will process the records asynchronously and notify you upon completion.
