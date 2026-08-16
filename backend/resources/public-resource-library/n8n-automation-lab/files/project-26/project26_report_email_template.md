# Daily Business Report — {{ report_date }}

## Executive brief
{{ ai_summary }}

## Today at a glance
- New leads: {{ sales.leads_new }}
- Deals won: {{ sales.deals_won }}
- Ecommerce orders: {{ ecommerce.orders_paid }}
- Ecommerce revenue: {{ ecommerce.revenue }}
- Cash collected: {{ invoices.paid_total }}
- Outstanding A/R: {{ invoices.outstanding_ar }}
- Feedback responses: {{ feedback.responses }}

## Needs attention
{{ anomalies }}

## Data quality
Coverage: {{ source_coverage }}%
{{ data_quality_note }}
