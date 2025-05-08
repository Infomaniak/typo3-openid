CREATE TABLE fe_users
(
	infomaniak_auth_id varchar(100) DEFAULT '' NOT NULL,
	KEY                fk_infomaniak (infomaniak_auth_id)
);

CREATE TABLE be_users
(
	infomaniak_auth_id varchar(100) DEFAULT '' NOT NULL,
	KEY                fk_infomaniak (infomaniak_auth_id)
);
