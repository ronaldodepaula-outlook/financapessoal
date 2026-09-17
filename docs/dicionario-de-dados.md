# Dicionário do banco aplicado

Gerado das migrations aplicadas no MariaDB local em 14/09/2026. Valores são metadados, não dados financeiros. Relacionamentos e índices estão nas migrations.

## accounts

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| name | varchar(120) | NO | — |  |
| institution | varchar(120) | YES | NULL |  |
| account_type | enum('CONTA_CORRENTE','POUPANCA','CARTEIRA','CONTA_DIGITAL','INVESTIMENTO') | NO | — |  |
| initial_balance | decimal(15,2) | NO | 0.00 |  |
| status | enum('ATIVO','INATIVO') | NO | 'ATIVO' |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| deleted_at | timestamp | YES | NULL |  |

## audit_logs

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| action | varchar(80) | NO | — |  |
| entity_type | varchar(120) | NO | — |  |
| entity_id | bigint(20) unsigned | YES | NULL |  |
| changes | longtext | YES | NULL |  |
| request_id | varchar(36) | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## auth_sessions

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | char(36) | NO | — |  |
| user_id | bigint(20) unsigned | NO | — |  |
| refresh_token_hash | char(64) | NO | — |  |
| version | int(10) unsigned | NO | 1 |  |
| expires_at | datetime | NO | — |  |
| revoked_at | timestamp | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## budgets

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| year | smallint(5) unsigned | NO | — |  |
| month | tinyint(3) unsigned | NO | — |  |
| category_id | bigint(20) unsigned | NO | — |  |
| subcategory_id | bigint(20) unsigned | YES | NULL |  |
| subcategory_scope | bigint(20) unsigned | YES | NULL | STORED GENERATED |
| planned_amount | decimal(15,2) | NO | — |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## budget_revisions

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| budget_id | bigint(20) unsigned | NO | — |  |
| version | int(10) unsigned | NO | — |  |
| previous_amount | decimal(15,2) | NO | — |  |
| new_amount | decimal(15,2) | NO | — |  |
| reason | text | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## cache

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| key | varchar(255) | NO | — |  |
| value | mediumtext | NO | — |  |
| expiration | int(11) | NO | — |  |

## cache_locks

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| key | varchar(255) | NO | — |  |
| owner | varchar(255) | NO | — |  |
| expiration | int(11) | NO | — |  |

## cards

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| name | varchar(120) | NO | — |  |
| institution | varchar(120) | YES | NULL |  |
| brand | varchar(40) | YES | NULL |  |
| last_digits | varchar(4) | YES | NULL |  |
| credit_limit | decimal(15,2) | NO | — |  |
| closing_day | tinyint(3) unsigned | NO | — |  |
| due_day | tinyint(3) unsigned | NO | — |  |
| status | enum('ATIVO','INATIVO') | NO | 'ATIVO' |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| deleted_at | timestamp | YES | NULL |  |

## card_invoices

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| card_id | bigint(20) unsigned | NO | — |  |
| competence_year | smallint(5) unsigned | NO | — |  |
| competence_month | tinyint(3) unsigned | NO | — |  |
| closing_date | date | NO | — |  |
| due_date | date | NO | — |  |
| status | enum('PENDENTE','PAGA','CANCELADA') | NO | 'PENDENTE' |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## card_invoice_payments

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| card_invoice_id | bigint(20) unsigned | NO | — |  |
| account_id | bigint(20) unsigned | NO | — |  |
| amount | decimal(15,2) | NO | — |  |
| payment_date | date | NO | — |  |
| status | enum('PENDENTE','PAGA','CANCELADA') | NO | 'PENDENTE' |  |
| notes | text | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## categories

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| name | varchar(120) | NO | — |  |
| type | enum('RECEITA','DESPESA') | NO | — |  |
| parent_id | bigint(20) unsigned | YES | NULL |  |
| parent_scope | bigint(20) unsigned | YES | NULL | STORED GENERATED |
| status | enum('ATIVO','INATIVO') | NO | 'ATIVO' |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| deleted_at | timestamp | YES | NULL |  |

## financial_goals

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| name | varchar(160) | NO | — |  |
| target_amount | decimal(15,2) | NO | — |  |
| target_date | date | YES | NULL |  |
| initial_amount | decimal(15,2) | NO | 0.00 |  |
| status | enum('ATIVA','CONCLUIDA','CANCELADA') | NO | 'ATIVA' |  |
| notes | text | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| deleted_at | timestamp | YES | NULL |  |

## fixed_expenses

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| category_id | bigint(20) unsigned | NO | — |  |
| subcategory_id | bigint(20) unsigned | YES | NULL |  |
| account_id | bigint(20) unsigned | YES | NULL |  |
| card_id | bigint(20) unsigned | YES | NULL |  |
| description | varchar(255) | NO | — |  |
| amount | decimal(15,2) | NO | — |  |
| due_day | tinyint(3) unsigned | NO | — |  |
| payment_method | enum('PIX','DINHEIRO','DEBITO','CREDITO','TRANSFERENCIA','BOLETO','OUTROS') | NO | — |  |
| active | tinyint(1) | NO | 1 |  |
| start_date | date | NO | — |  |
| end_date | date | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| deleted_at | timestamp | YES | NULL |  |

## goal_contributions

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| financial_goal_id | bigint(20) unsigned | NO | — |  |
| transfer_id | bigint(20) unsigned | YES | NULL |  |
| amount | decimal(15,2) | NO | — |  |
| contribution_date | date | NO | — |  |
| notes | text | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| cancelled_at | datetime | YES | NULL |  |

## imports

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| original_filename | varchar(255) | NO | — |  |
| stored_path | varchar(255) | NO | — |  |
| file_hash | varchar(64) | NO | — |  |
| format | enum('CSV','OFX') | NO | — |  |
| account_id | bigint(20) unsigned | YES | NULL |  |
| card_id | bigint(20) unsigned | YES | NULL |  |
| column_mapping | longtext | YES | NULL |  |
| status | enum('PREVIA','PROCESSANDO','CONCLUIDA','FALHOU') | NO | 'PREVIA' |  |
| confirmed_at | timestamp | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## import_rows

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| import_id | bigint(20) unsigned | NO | — |  |
| transaction_id | bigint(20) unsigned | YES | NULL |  |
| row_number | int(10) unsigned | NO | — |  |
| raw_data | longtext | YES | NULL |  |
| mapped_data | longtext | YES | NULL |  |
| fingerprint | varchar(64) | YES | NULL |  |
| status | enum('PENDENTE','IMPORTADA','DUPLICADA','INVALIDA') | NO | 'PENDENTE' |  |
| validation_errors | longtext | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## income_schedules

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| description | varchar(255) | NO | — |  |
| amount | decimal(15,2) | NO | — |  |
| day | tinyint(3) unsigned | YES | NULL |  |
| period | enum('01_15','16_31') | NO | — |  |
| account_id | bigint(20) unsigned | YES | NULL |  |
| category_id | bigint(20) unsigned | YES | NULL |  |
| active | tinyint(1) | NO | 0 |  |
| start_date | date | YES | NULL |  |
| end_date | date | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| deleted_at | timestamp | YES | NULL |  |
| template_key | varchar(80) | YES | NULL |  |

## installments

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| account_id | bigint(20) unsigned | YES | NULL |  |
| card_id | bigint(20) unsigned | YES | NULL |  |
| category_id | bigint(20) unsigned | NO | — |  |
| subcategory_id | bigint(20) unsigned | YES | NULL |  |
| description | varchar(255) | NO | — |  |
| total_amount | decimal(15,2) | NO | — |  |
| installment_amount | decimal(15,2) | NO | — |  |
| total_installments | smallint(5) unsigned | NO | — |  |
| start_date | date | NO | — |  |
| end_date | date | NO | — |  |
| status | enum('PENDENTE','PAGA','CANCELADA') | NO | 'PENDENTE' |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## loans

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| name | varchar(160) | NO | — |  |
| institution | varchar(120) | YES | NULL |  |
| loan_type | enum('PESSOAL','CONSIGNADO','FINANCIAMENTO','OUTROS') | NO | — |  |
| principal_amount | decimal(15,2) | NO | — |  |
| interest_rate | decimal(8,4) | YES | NULL |  |
| cet | decimal(8,4) | YES | NULL |  |
| annual_cet | decimal(8,4) | YES | NULL |  |
| iof | decimal(15,2) | YES | NULL |  |
| installments | smallint(5) unsigned | NO | — |  |
| installment_amount | decimal(15,2) | NO | — |  |
| start_date | date | YES | NULL |  |
| first_due_date | date | YES | NULL |  |
| end_date | date | YES | NULL |  |
| status | enum('RASCUNHO','ATIVO','QUITADO','CANCELADO') | NO | 'RASCUNHO' |  |
| notes | text | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| category_id | bigint(20) unsigned | YES | NULL |  |
| subcategory_id | bigint(20) unsigned | YES | NULL |  |
| account_id | bigint(20) unsigned | YES | NULL |  |
| cash_flow_mode | enum('CONTA','FOLHA') | YES | NULL |  |
| template_key | varchar(80) | YES | NULL |  |

## loan_balance_snapshots

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| loan_id | bigint(20) unsigned | NO | — |  |
| outstanding_balance | decimal(15,2) | NO | — |  |
| reported_at | date | NO | — |  |
| notes | text | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## loan_installments

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| loan_id | bigint(20) unsigned | NO | — |  |
| transaction_id | bigint(20) unsigned | YES | NULL |  |
| number | smallint(5) unsigned | NO | — |  |
| due_date | date | NO | — |  |
| amount | decimal(15,2) | NO | — |  |
| principal_component | decimal(15,2) | YES | NULL |  |
| interest_component | decimal(15,2) | YES | NULL |  |
| status | enum('PENDENTE','PAGA','CANCELADA') | NO | 'PENDENTE' |  |
| paid_at | timestamp | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| settled_by_payment_id | bigint(20) unsigned | YES | NULL |  |

## loan_payments

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| loan_id | bigint(20) unsigned | NO | — |  |
| loan_installment_id | bigint(20) unsigned | YES | NULL |  |
| account_id | bigint(20) unsigned | YES | NULL |  |
| payment_type | enum('PARCELA','AMORTIZACAO','QUITACAO') | NO | — |  |
| amount | decimal(15,2) | NO | — |  |
| payment_date | date | NO | — |  |
| reference | varchar(120) | YES | NULL |  |
| status | enum('PENDENTE','PAGA','CANCELADA') | NO | 'PENDENTE' |  |
| notes | text | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| transaction_id | bigint(20) unsigned | YES | NULL |  |

## merchants

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| name | varchar(160) | NO | — |  |
| normalized_name | varchar(160) | NO | — |  |
| category_id | bigint(20) unsigned | YES | NULL |  |
| subcategory_id | bigint(20) unsigned | YES | NULL |  |
| status | enum('ATIVO','INATIVO') | NO | 'ATIVO' |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| deleted_at | timestamp | YES | NULL |  |

## migrations

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | int(10) unsigned | NO | — | auto_increment |
| migration | varchar(255) | NO | — |  |
| batch | int(11) | NO | — |  |

## subscriptions

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| name | varchar(160) | NO | — |  |
| category_id | bigint(20) unsigned | NO | — |  |
| subcategory_id | bigint(20) unsigned | YES | NULL |  |
| amount | decimal(15,2) | NO | — |  |
| billing_cycle | enum('MENSAL','TRIMESTRAL','SEMESTRAL','ANUAL') | NO | — |  |
| next_due_date | date | NO | — |  |
| payment_method | enum('PIX','DINHEIRO','DEBITO','CREDITO','TRANSFERENCIA','BOLETO','OUTROS') | NO | — |  |
| account_id | bigint(20) unsigned | YES | NULL |  |
| card_id | bigint(20) unsigned | YES | NULL |  |
| active | tinyint(1) | NO | 1 |  |
| start_date | date | NO | — |  |
| end_date | date | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| deleted_at | timestamp | YES | NULL |  |
| billing_anchor_date | date | YES | NULL |  |

## transactions

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| account_id | bigint(20) unsigned | YES | NULL |  |
| card_id | bigint(20) unsigned | YES | NULL |  |
| category_id | bigint(20) unsigned | NO | — |  |
| subcategory_id | bigint(20) unsigned | YES | NULL |  |
| merchant_id | bigint(20) unsigned | YES | NULL |  |
| card_invoice_id | bigint(20) unsigned | YES | NULL |  |
| transaction_type | enum('RECEITA','DESPESA') | NO | — |  |
| description | varchar(255) | NO | — |  |
| transaction_date | date | NO | — |  |
| due_date | date | YES | NULL |  |
| competence_month | tinyint(3) unsigned | NO | — |  |
| competence_year | smallint(5) unsigned | NO | — |  |
| amount | decimal(15,2) | NO | — |  |
| payment_method | enum('PIX','DINHEIRO','DEBITO','CREDITO','TRANSFERENCIA','BOLETO','OUTROS') | NO | — |  |
| is_fixed | tinyint(1) | NO | 0 |  |
| is_installment | tinyint(1) | NO | 0 |  |
| installment_id | bigint(20) unsigned | YES | NULL |  |
| installment_number | smallint(5) unsigned | YES | NULL |  |
| fixed_expense_id | bigint(20) unsigned | YES | NULL |  |
| subscription_id | bigint(20) unsigned | YES | NULL |  |
| income_schedule_id | bigint(20) unsigned | YES | NULL |  |
| status | enum('PENDENTE','PAGA','CANCELADA') | NO | 'PENDENTE' |  |
| paid_at | timestamp | YES | NULL |  |
| notes | text | YES | NULL |  |
| import_fingerprint | varchar(64) | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| loan_id | bigint(20) unsigned | YES | NULL |  |
| cash_flow_effect | tinyint(1) | NO | 1 |  |

## transfers

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| source_account_id | bigint(20) unsigned | NO | — |  |
| destination_account_id | bigint(20) unsigned | NO | — |  |
| amount | decimal(15,2) | NO | — |  |
| transfer_date | date | NO | — |  |
| description | varchar(255) | YES | NULL |  |
| status | enum('PENDENTE','PAGA','CANCELADA') | NO | 'PENDENTE' |  |
| notes | text | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## users

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| name | varchar(120) | NO | — |  |
| email | varchar(255) | NO | — |  |
| password | varchar(255) | NO | — |  |
| status | enum('ATIVO','INATIVO') | NO | 'ATIVO' |  |
| email_verified_at | timestamp | YES | NULL |  |
| remember_token | varchar(100) | YES | NULL |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |

## user_preferences

| Coluna | Tipo | Aceita NULL | Padrão | Extra |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | auto_increment |
| user_id | bigint(20) unsigned | NO | — |  |
| income_is_net_of_payroll_loan | tinyint(1) | YES | NULL |  |
| timezone | varchar(64) | NO | — |  |
| currency | varchar(3) | NO | — |  |
| created_at | timestamp | YES | NULL |  |
| updated_at | timestamp | YES | NULL |  |
| planning_initialized_at | datetime | YES | NULL |  |
