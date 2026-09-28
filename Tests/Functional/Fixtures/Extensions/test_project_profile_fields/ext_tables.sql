CREATE TABLE tx_academicpersons_domain_model_profile (
    tx_test_prefix varchar(30) DEFAULT '' NOT NULL,
    tx_test_guest smallint(5) unsigned DEFAULT '0' NOT NULL,
    tx_test_code varchar(255) DEFAULT '' NOT NULL,
    tx_test_synced varchar(255) DEFAULT '' NOT NULL,
    tx_test_note text,
    tx_test_shared varchar(255) DEFAULT '' NOT NULL,
    tx_test_own varchar(255) DEFAULT '' NOT NULL,
    tx_test_mail varchar(255) DEFAULT '' NOT NULL,
    tx_test_web varchar(1024) DEFAULT '' NOT NULL
);
