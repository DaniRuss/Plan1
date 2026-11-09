# Approved plan export branding

PDF downloads for organization plans, department plans and individual objectives now use a shared branded header, footer, section colors and table colors. Generated organization-plan Word documents use the same logo, name, abbreviation and color palette. Uploaded attachments are downloaded in their original form.

Choose Settings > Logo & Branding > Document Brand Color. Default: #277967, matching the system logo. Uploaded PNG/JPEG logos are embedded from private server-local paths; no external image download or public storage symlink is required by the PDF/Word exporter. Fallback: public/images/system-logo.png.

Upload the following files to their matching paths in the deployed Laravel project, then clear compiled Blade views. No database migration or Composer update is required. Existing plan data is unchanged.

- app/Services/DocumentBranding.php
- app/Services/OrganizationPlanWordExporter.php
- app/Http/Controllers/SettingController.php
- resources/views/settings/index.blade.php
- resources/views/edr_plans/organization_approved_plans_pdf.blade.php
- resources/views/edr_plans/organization_plan_pdf.blade.php
- resources/views/edr_plans/plan_pdf.blade.php
- resources/views/edr_plans/objectives_pdf.blade.php
- resources/views/edr_plans/partials/branding.blade.php
- resources/views/edr_plans/partials/header.blade.php
- public/images/system-logo.png

Verification: EdrPlanDocumentTest covers existing PDF downloads and Word branding; a multi-page PDF is rendered for layout review. Test fixture exports under tmp/pdfs are samples, not real approved reports.

## Schedule layout

The repeated Department column is removed; the department appears once in the report subtitle for a single-department schedule, or as a plain section heading for multiple departments. Generated plan titles are omitted from section headings. Implementing Departments lists the lead first, marked (Lead), then all distinct contributors from the objective and plan item. Schedules use July, August, September, Q1, October, November, December, Q2, January, February, March, Q3, April, May, June, Q4. PDF and Word use ApprovedPlanExport for the same ordering and department resolution.

Additional files for the layout update:
- app/Services/ApprovedPlanExport.php
- app/Http/Controllers/EdrPlanController.php
- resources/views/edr_plans/partials/schedule.blade.php
- resources/views/edr_plans/_objective_pdf_row.blade.php

## Report heading cleanup

The approved-plan PDF uses Approved Annual Plan (year), a short department/period subtitle, and no explanatory preview notice. Department headings in PDF and Word omit generated plan titles. Deploy organization_approved_plans_pdf.blade.php, organization_plan_pdf.blade.php, partials/schedule.blade.php and OrganizationPlanWordExporter.php for this cleanup.

## Combined organization schedule

Department plans are combined into one naturally ascending objective-code list, without department section rows. Shared objective IDs appear once. The lead department approved plan supplies targets (otherwise the newest plan ID); duplicate targets are never summed. Implementing departments combine all copies. PDF summary, PDF schedule and generated Word exports share this logic.

Deploy app/Services/ApprovedPlanExport.php, app/Services/OrganizationPlanWordExporter.php, resources/views/edr_plans/partials/schedule.blade.php and resources/views/edr_plans/organization_plan_pdf.blade.php. The ZIP also includes the current approved-plan title template. No migration is required.
