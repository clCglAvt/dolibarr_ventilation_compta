--
-- Be carefull to requests order.
-- This file must be loaded by calling /install/index.php page
-- when current version is 4.0.0 or higher.
--
-- To rename a table:       ALTER TABLE llx_table RENAME TO llx_table_new;
-- To add a column:         ALTER TABLE llx_table ADD COLUMN newcol varchar(60) NOT NULL DEFAULT '0' AFTER existingcol;
-- To rename a column:      ALTER TABLE llx_table CHANGE COLUMN oldname newname varchar(60);
-- To drop a column:        ALTER TABLE llx_table DROP COLUMN oldname;
-- To change type of field: ALTER TABLE llx_table MODIFY COLUMN name varchar(60);
-- To set a DEFAULT value:  ALTER TABLE llx_table ALTER COLUMN name SET DEFAULT (0|NULL|...);
-- To drop a foreign key:   ALTER TABLE llx_table DROP FOREIGN KEY fk_name;
-- To drop an index:        -- VMYSQL4.0 DROP INDEX nomindex on llx_table
-- To drop an index:        -- VPGSQL8.0 DROP INDEX nomindex
-- To restrict request to Mysql version x.y minimum use -- VMYSQLx.y
-- To restrict request to Pgsql version x.y minimum use -- VPGSQLx.y
-- To make pk to be auto increment (mysql):    -- VMYSQL4.3 ALTER TABLE llx_c_shipment_mode CHANGE COLUMN rowid rowid INTEGER NOT NULL AUTO_INCREMENT;
-- To make pk to be auto increment (postgres): -- VPGSQL8.2 NOT POSSIBLE. MUST DELETE/CREATE TABLE
-- To set a field as NULL:                     -- VMYSQL4.3 ALTER TABLE llx_table MODIFY COLUMN name varchar(60) NULL;
-- To set a field as NULL:                     -- VPGSQL8.2 ALTER TABLE llx_table ALTER COLUMN name DROP NOT NULL;
-- To set a field as NOT NULL:                 -- VMYSQL4.3 ALTER TABLE llx_table MODIFY COLUMN name varchar(60) NOT NULL;
-- To set a field as NOT NULL:                 -- VPGSQL8.2 ALTER TABLE llx_table ALTER COLUMN name SET NOT NULL;
-- Note: fields with type BLOB/TEXT can't have default value.
-- -- VPGSQL8.2 DELETE FROM llx_usergroup_user      WHERE fk_user      NOT IN (SELECT rowid from llx_user);
-- -- VMYSQL4.1 DELETE FROM llx_usergroup_user      WHERE fk_usergroup NOT IN (SELECT rowid from llx_usergroup);

-- after changing const name, please insure that old constant was rename
UPDATE llx_const SET name = __ENCRYPT('THIRDPARTY_DEFAULT_CREATE_CONTACT')__ WHERE name = __ENCRYPT('MAIN_THIRPARTY_CREATION_INDIVIDUAL')__;  -- under 3.9.0
UPDATE llx_const SET name = __ENCRYPT('THIRDPARTY_DEFAULT_CREATE_CONTACT')__ WHERE name = __ENCRYPT('MAIN_THIRDPARTY_CREATION_INDIVIDUAL')__; -- under 4.0.1

-- VPGSQL8.2 ALTER TABLE llx_product_lot ALTER COLUMN entity SET DEFAULT 1;
ALTER TABLE llx_product_lot MODIFY COLUMN entity integer DEFAULT 1;
UPDATE llx_product_lot SET entity = 1 WHERE entity IS NULL;

ALTER TABLE llx_bank_account ADD COLUMN extraparams		varchar(255);	

ALTER TABLE llx_societe ALTER COLUMN fk_stcomm SET DEFAULT 0;

ALTER TABLE llx_c_actioncomm ADD COLUMN picto varchar(48);

ALTER TABLE llx_facturedet ADD INDEX idx_facturedet_fk_code_ventilation (fk_code_ventilation);
ALTER TABLE llx_facture_fourn_det ADD INDEX idx_facture_fourn_det_fk_code_ventilation (fk_code_ventilation);

ALTER TABLE llx_facture_fourn_det ADD INDEX idx_facture_fourn_det_fk_product (fk_product);

ALTER TABLE llx_facture_rec ADD COLUMN fk_user_modif integer;
ALTER TABLE llx_expedition ADD COLUMN fk_user_modif integer;
ALTER TABLE llx_projet ADD COLUMN fk_user_modif integer;

ALTER TABLE llx_adherent ADD COLUMN model_pdf varchar(255);

ALTER TABLE llx_don ADD COLUMN date_valid datetime;

DELETE FROM llx_menu where module='expensereport';

ALTER TABLE llx_facturedet ADD COLUMN fk_user_author integer after fk_unit;
ALTER TABLE llx_facturedet ADD COLUMN fk_user_modif integer after fk_unit;

ALTER TABLE llx_user DROP COLUMN phenix_login;
ALTER TABLE llx_user DROP COLUMN phenix_pass;
ALTER TABLE llx_user ADD COLUMN dateemployment datetime;

ALTER TABLE llx_societe ADD COLUMN fk_account integer;

ALTER TABLE llx_commandedet ADD COLUMN fk_commandefourndet integer DEFAULT NULL after import_key;   -- link to detail line of commande fourn (resplenish)
ALTER TABLE llx_commandedet MODIFY COLUMN fk_commandefourndet integer DEFAULT NULL;

ALTER TABLE llx_website ADD COLUMN virtualhost varchar(255) after fk_default_home;

ALTER TABLE llx_chargesociales ADD COLUMN fk_account integer after fk_type;
ALTER TABLE llx_chargesociales ADD COLUMN fk_mode_reglement integer after fk_account;
ALTER TABLE llx_chargesociales ADD COLUMN fk_user_author		integer;
ALTER TABLE llx_chargesociales ADD COLUMN fk_user_modif         integer;
ALTER TABLE llx_chargesociales ADD COLUMN fk_user_valid			integer;


ALTER TABLE llx_ecm_files ADD COLUMN gen_or_uploaded varchar(12) after cover; 

DROP TABLE llx_document_generator;
DROP TABLE llx_ecm_documents;
DROP TABLE llx_holiday_events;
DROP TABLE llx_holiday_types;

ALTER TABLE llx_notify ADD COLUMN type_target varchar(16) NULL;

ALTER TABLE llx_entrepot DROP COLUMN valo_pmp;

ALTER TABLE llx_notify_def MODIFY COLUMN fk_soc integer NULL;
-- VPGSQL8.2 ALTER TABLE llx_notify_def ALTER COLUMN fk_soc SET DEFAULT NULL;


create table llx_categorie_project
(
  fk_categorie  integer NOT NULL,
  fk_project    integer NOT NULL,
  import_key    varchar(14)
)ENGINE=innodb;

ALTER TABLE llx_categorie_project ADD PRIMARY KEY pk_categorie_project (fk_categorie, fk_project);
ALTER TABLE llx_categorie_project ADD INDEX idx_categorie_project_fk_categorie (fk_categorie);
ALTER TABLE llx_categorie_project ADD INDEX idx_categorie_project_fk_project (fk_project);

ALTER TABLE llx_categorie_project ADD CONSTRAINT fk_categorie_project_categorie_rowid FOREIGN KEY (fk_categorie) REFERENCES llx_categorie (rowid);
ALTER TABLE llx_categorie_project ADD CONSTRAINT fk_categorie_project_fk_project_rowid FOREIGN KEY (fk_project) REFERENCES llx_projet (rowid);

ALTER TABLE llx_societe_remise_except ADD COLUMN entity	integer DEFAULT 1 NOT NULL after rowid;
ALTER TABLE llx_societe_remise ADD COLUMN entity	integer DEFAULT 1 NOT NULL after rowid;


create table llx_expensereport_extrafields
(
  rowid                     integer AUTO_INCREMENT PRIMARY KEY,
  tms                       timestamp,
  fk_object                 integer NOT NULL,
  import_key                varchar(14)                          		-- import key
) ENGINE=innodb;

ALTER TABLE llx_expensereport_extrafields ADD INDEX idx_expensereport_extrafields (fk_object);

ALTER TABLE llx_cotisation RENAME TO llx_subscription;
ALTER TABLE llx_subscription ADD UNIQUE INDEX uk_subscription (fk_adherent,dateadh);
ALTER TABLE llx_subscription CHANGE COLUMN cotisation subscription real;
ALTER TABLE llx_adherent_type CHANGE COLUMN cotisation subscription varchar(3) NOT NULL DEFAULT '1';

UPDATE llx_adherent_type SET subscription = '1' WHERE subscription = 'yes';

CREATE TABLE llx_product_lot_extrafields
(
  rowid                     integer AUTO_INCREMENT PRIMARY KEY,
  tms                       timestamp,
  fk_object                 integer NOT NULL,
  import_key                varchar(14)                          		-- import key
) ENGINE=innodb;

ALTER TABLE llx_product_lot_extrafields ADD INDEX idx_product_lot_extrafields (fk_object);

ALTER TABLE llx_website_page MODIFY content MEDIUMTEXT;

CREATE TABLE llx_product_warehouse_properties
(
  rowid           		integer AUTO_INCREMENT PRIMARY KEY,
  tms             		timestamp,
  fk_product      		integer NOT NULL,
  fk_entrepot     		integer NOT NULL,
  seuil_stock_alerte    integer DEFAULT 0,
  desiredstock    		integer DEFAULT 0,
  import_key      		varchar(14)               -- Import key
)ENGINE=innodb;

ALTER TABLE llx_accounting_bookkeeping ADD COLUMN entity integer DEFAULT 1 NOT NULL;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN fk_user_modif     integer;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN date_creation		datetime;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN tms               timestamp;
-- VMYSQL4.3 ALTER TABLE llx_accounting_bookkeeping MODIFY COLUMN numero_compte varchar(32) NOT NULL;
-- VMYSQL4.3 ALTER TABLE llx_accounting_bookkeeping MODIFY COLUMN code_journal varchar(32) NOT NULL;
-- VPGSQL8.2 ALTER TABLE llx_accounting_bookkeeping ALTER COLUMN numero_compte SET NOT NULL;
-- VPGSQL8.2 ALTER TABLE llx_accounting_bookkeeping ALTER COLUMN code_journal SET NOT NULL;

ALTER TABLE llx_accounting_account ADD UNIQUE INDEX uk_accounting_account (account_number, entity, fk_pcg_version);

ALTER TABLE llx_expensereport_det ADD COLUMN fk_code_ventilation integer DEFAULT 0;

ALTER TABLE llx_c_payment_term change fdm type_cdr tinyint;


ALTER TABLE llx_facturedet ADD COLUMN vat_src_code varchar(10) DEFAULT '' AFTER tva_tx;
ALTER TABLE llx_facturedet_rec ADD COLUMN vat_src_code varchar(10) DEFAULT '' AFTER tva_tx;
ALTER TABLE llx_facture_fourn_det ADD COLUMN vat_src_code varchar(10) DEFAULT '' AFTER tva_tx;
ALTER TABLE llx_commandedet ADD COLUMN vat_src_code varchar(10) DEFAULT '' AFTER tva_tx;
ALTER TABLE llx_commande_fournisseurdet ADD COLUMN vat_src_code varchar(10) DEFAULT '' AFTER tva_tx;
ALTER TABLE llx_propaldet ADD COLUMN vat_src_code varchar(10) DEFAULT '' AFTER tva_tx;
ALTER TABLE llx_supplier_proposaldet ADD COLUMN vat_src_code varchar(10) DEFAULT '' AFTER tva_tx;
ALTER TABLE llx_supplier_proposaldet ADD COLUMN fk_unit integer DEFAULT NULL;
ALTER TABLE llx_contratdet ADD COLUMN vat_src_code varchar(10) DEFAULT '' AFTER tva_tx;

ALTER TABLE llx_c_payment_term change fdm type_cdr tinyint;

ALTER TABLE llx_entrepot ADD COLUMN fk_parent integer DEFAULT 0;


create table llx_resource_extrafields
(
  rowid                     integer AUTO_INCREMENT PRIMARY KEY,
  tms                       timestamp,
  fk_object                 integer NOT NULL,
  import_key                varchar(14)                          		-- import key
) ENGINE=innodb;

ALTER TABLE llx_resource_extrafields ADD INDEX idx_resource_extrafields (fk_object);

INSERT INTO llx_const (name, value, type, note, visible, entity) values (__ENCRYPT('MAIN_SIZE_SHORTLIST_LIMIT')__, __ENCRYPT('3')__, 'chaine', 'Max length for small lists (tabs)', 0, 0);

INSERT INTO llx_const (name, value, type, note, visible, entity) values (__ENCRYPT('EXPEDITION_ADDON_NUMBER')__, __ENCRYPT('mod_expedition_safor')__, 'chaine','Name for numbering manager for shipments',0,1);

ALTER TABLE llx_bank_account ADD COLUMN note_public     		text;
ALTER TABLE llx_bank_account ADD COLUMN model_pdf       		varchar(255);
ALTER TABLE llx_bank_account ADD COLUMN import_key      		varchar(14);

ALTER TABLE llx_projet ADD COLUMN import_key      	        	varchar(14);
ALTER TABLE llx_projet_task ADD COLUMN import_key      		    varchar(14);
ALTER TABLE llx_projet_task_time ADD COLUMN import_key      	varchar(14);


ALTER TABLE llx_overwrite_trans ADD COLUMN entity integer DEFAULT 1 NOT NULL AFTER rowid;

ALTER TABLE llx_mailing_cibles ADD COLUMN error_text varchar(255);

ALTER TABLE llx_c_actioncomm MODIFY COLUMN type varchar(50) DEFAULT 'system' NOT NULL;

create table llx_user_employment
(
  rowid             integer AUTO_INCREMENT PRIMARY KEY,
  entity            integer DEFAULT 1 NOT NULL, -- multi company id
  ref				varchar(50),				-- reference
  ref_ext			varchar(50),				-- reference into an external system (not used by dolibarr)
  fk_user			integer,
  datec             datetime,
  tms               timestamp,
  fk_user_creat     integer,
  fk_user_modif     integer,
  job				varchar(128),				-- job position. may be a dictionnary
  status            integer NOT NULL,			-- draft, active, closed
  salary			double(24,8),				-- last and current value stored into llx_user
  salaryextra		double(24,8),				-- last and current value stored into llx_user
  weeklyhours		double(16,8),				-- last and current value stored into llx_user
  dateemployment    date,						-- last and current value stored into llx_user
  dateemploymentend date						-- last and current value stored into llx_user
)ENGINE=innodb;


ALTER TABLE llx_expensereport ADD INDEX idx_expensereport_date_debut (date_debut);
ALTER TABLE llx_expensereport ADD INDEX idx_expensereport_date_fin (date_fin);
ALTER TABLE llx_expensereport ADD INDEX idx_expensereport_fk_statut (fk_statut);

ALTER TABLE llx_expensereport ADD INDEX idx_expensereport_fk_user_author (fk_user_author);
ALTER TABLE llx_expensereport ADD INDEX idx_expensereport_fk_user_valid (fk_user_valid);
ALTER TABLE llx_expensereport ADD INDEX idx_expensereport_fk_user_approve (fk_user_approve);
ALTER TABLE llx_expensereport ADD INDEX idx_expensereport_fk_refuse (fk_user_approve);

DELETE FROM llx_actioncomm_resources WHERE fk_actioncomm not in (select id from llx_actioncomm);

-- Sequence to removed duplicated values of llx_links. Use serveral times if you still have duplicate.
drop table tmp_links_double;
--select objectid, label, max(rowid) as max_rowid, count(rowid) as count_rowid from llx_links where label is not null group by objectid, label having count(rowid) >= 2;
create table tmp_links_double as (select objectid, label, max(rowid) as max_rowid, count(rowid) as count_rowid from llx_links where label is not null group by objectid, label having count(rowid) >= 2);
--select * from tmp_links_double;
delete from llx_links where (rowid, label) in (select max_rowid, label from tmp_links_double);	--update to avoid duplicate, delete to delete
drop table tmp_links_double;

ALTER TABLE llx_links ADD UNIQUE INDEX uk_links (objectid,label);

ALTER TABLE llx_expensereport ADD UNIQUE INDEX idx_expensereport_uk_ref (ref, entity);

UPDATE llx_projet_task SET ref = NULL WHERE ref = '';
ALTER TABLE llx_projet_task ADD UNIQUE INDEX uk_projet_task_ref (ref, entity);

ALTER TABLE llx_contrat ADD COLUMN fk_user_modif integer;


update llx_accounting_account set account_parent = 0 where account_parent = '';

-- VMYSQL4.3 ALTER TABLE llx_product_price MODIFY COLUMN date_price DATETIME NULL;
-- VPGSQL8.2 ALTER TABLE llx_product_price ALTER COLUMN date_price DROP NOT NULL;
ALTER TABLE llx_product_price ALTER COLUMN date_price SET DEFAULT NULL;
 
ALTER TABLE llx_product_price ADD COLUMN default_vat_code	varchar(10) after tva_tx;
ALTER TABLE llx_product_customer_price ADD COLUMN default_vat_code	varchar(10) after tva_tx;
ALTER TABLE llx_product_customer_price_log ADD COLUMN default_vat_code	varchar(10) after tva_tx;
ALTER TABLE llx_product_fournisseur_price ADD COLUMN default_vat_code	varchar(10) after tva_tx;


ALTER TABLE llx_events MODIFY COLUMN ip varchar(250);

ALTER TABLE llx_bank_account drop foreign key bank_fk_accountancy_journal;

-- Fix missing entity column after init demo
ALTER TABLE llx_accounting_journal ADD COLUMN entity integer DEFAULT 1;

-- Add journal entries
INSERT INTO llx_accounting_journal (rowid, code, label, nature, active) VALUES (1,'VT', 'Sale journal', 2, 1);
INSERT INTO llx_accounting_journal (rowid, code, label, nature, active) VALUES (2,'AC', 'Purchase journal', 3, 1);
INSERT INTO llx_accounting_journal (rowid, code, label, nature, active) VALUES (3,'BQ', 'Bank journal', 4, 1);
INSERT INTO llx_accounting_journal (rowid, code, label, nature, active) VALUES (4,'OD', 'Other journal', 1, 1);
INSERT INTO llx_accounting_journal (rowid, code, label, nature, active) VALUES (5,'AN', 'Has new journal', 9, 1);
INSERT INTO llx_accounting_journal (rowid, code, label, nature, active) VALUES (6,'ER', 'Expense report journal', 5, 1);
-- Fix old entries
UPDATE llx_accounting_journal SET nature = 1 where code = 'OD' and nature = 0;
UPDATE llx_accounting_journal SET nature = 2 where code = 'VT' and nature = 1;
UPDATE llx_accounting_journal SET nature = 3 where code = 'AC' and nature = 2;
UPDATE llx_accounting_journal SET nature = 4 where (code = 'BK' or code = 'BQ') and nature = 3;

UPDATE llx_bank_account as ba set accountancy_journal = 'BQ' where accountancy_journal = 'BK';
UPDATE llx_bank_account as ba set accountancy_journal = 'OD' where accountancy_journal IS NULL;

ALTER TABLE llx_bank_account ADD COLUMN fk_accountancy_journal integer;
ALTER TABLE llx_bank_account ADD INDEX idx_fk_accountancy_journal (fk_accountancy_journal);

UPDATE llx_bank_account as ba set fk_accountancy_journal = (SELECT rowid FROM llx_accounting_journal as aj where ba.accountancy_journal = aj.code) where accountancy_journal not in ('1', '2', '3', '4', '5', '6', '5', '8', '9', '10', '11', '12', '13', '14', '15');
ALTER TABLE llx_bank_account ADD CONSTRAINT fk_bank_account_accountancy_journal FOREIGN KEY (fk_accountancy_journal) REFERENCES llx_accounting_journal (rowid);

--Update general ledger for FEC format & harmonization
ALTER TABLE llx_accounting_bookkeeping MODIFY COLUMN code_tiers varchar(32);
ALTER TABLE llx_accounting_bookkeeping MODIFY COLUMN label_compte varchar(255);
ALTER TABLE llx_accounting_bookkeeping MODIFY COLUMN code_journal varchar(32);
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN thirdparty_label varchar(255) AFTER code_tiers;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN label_operation varchar(255) AFTER label_compte;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN multicurrency_amount double AFTER sens;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN multicurrency_code varchar(255) AFTER multicurrency_amount;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN lettering_code varchar(255) AFTER multicurrency_code;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN date_lettering datetime AFTER lettering_code;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN journal_label varchar(255) AFTER code_journal;
ALTER TABLE llx_accounting_bookkeeping ADD COLUMN date_validated datetime AFTER validated;

create table llx_payment_various
(
  rowid                 integer AUTO_INCREMENT PRIMARY KEY,
  tms                   timestamp,
  datec                 datetime,
  datep                 date,
  datev                 date,
  sens                  smallint DEFAULT 0 NOT NULL,
  amount                double(24,8) DEFAULT 0 NOT NULL,
  fk_typepayment        integer NOT NULL,
  num_payment           varchar(50),
  label                 varchar(255),
  accountancy_code		varchar(32),
  entity                integer DEFAULT 1 NOT NULL,
  note                  text,
  fk_bank               integer,
  fk_user_author        integer,
  fk_user_modif         integer
)ENGINE=innodb;



CREATE TABLE llx_accounting_bookkeeping_tmp
(
  rowid                 integer NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity                integer DEFAULT 1 NOT NULL,	-- 					| multi company id
  doc_date              date NOT NULL,				-- FEC:PieceDate
  doc_type              varchar(30) NOT NULL,		-- FEC:PieceRef		| facture_client/reglement_client/facture_fournisseur/reglement_fournisseur
  doc_ref               varchar(300) NOT NULL,		-- 					| facture_client/reglement_client/... reference number
  fk_doc                integer NOT NULL,			-- 					| facture_client/reglement_client/... rowid
  fk_docdet             integer NOT NULL,			-- 					| facture_client/reglement_client/... line rowid
  code_tiers            varchar(32),				-- FEC:CompAuxNum	| account number of auxiliary account
  thirdparty_label      varchar(255),				-- FEC:CompAuxLib	| label of auxiliary account
  numero_compte         varchar(32) NOT NULL,		-- FEC:CompteNum	| account number
  label_compte          varchar(255) NOT NULL,		-- FEC:CompteLib	| label of account
  label_operation       varchar(255),				-- FEC:EcritureLib	| label of the operation
  debit                 double NOT NULL,			-- FEC:Debit
  credit                double NOT NULL,			-- FEC:Credit
  montant               double NOT NULL,			-- FEC:Montant (Not necessary)
  sens                  varchar(1) DEFAULT NULL,	-- FEC:Sens (Not necessary)
  multicurrency_amount  double,						-- FEC:Montantdevise
  multicurrency_code    varchar(255),				-- FEC:Idevise
  lettering_code        varchar(255),				-- FEC:EcritureLet
  date_lettering        datetime,					-- FEC:DateLet
  fk_user_author        integer NOT NULL,			-- 					| user creating
  fk_user_modif         integer,					-- 					| user making last change
  date_creation         datetime,					-- FEC:EcritureDate	| creation date
  tms                   timestamp,					--					| date last modification 
  import_key            varchar(14),
  code_journal          varchar(32) NOT NULL,		-- FEC:JournalCode
  journal_label         varchar(255),				-- FEC:JournalLib
  piece_num             integer NOT NULL,			-- FEC:EcritureNum
  validated             tinyint DEFAULT 0 NOT NULL,	-- 					| 0 line not validated / 1 line validated (No deleting / No modification) 
  date_validated        datetime					-- FEC:ValidDate
) ENGINE=innodb;

ALTER TABLE llx_accounting_bookkeeping_tmp ADD INDEX idx_accounting_bookkeeping_doc_date (doc_date);
ALTER TABLE llx_accounting_bookkeeping_tmp ADD INDEX idx_accounting_bookkeeping_fk_docdet (fk_docdet);
ALTER TABLE llx_accounting_bookkeeping_tmp ADD INDEX idx_accounting_bookkeeping_numero_compte (numero_compte);
ALTER TABLE llx_accounting_bookkeeping_tmp ADD INDEX idx_accounting_bookkeeping_code_journal (code_journal);



