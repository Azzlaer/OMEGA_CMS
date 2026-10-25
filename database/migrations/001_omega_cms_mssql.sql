-- Omega MU CMS 2.0 - CMS-only schema
-- Safe by design: does not drop or alter MuOnline97 game tables.
IF OBJECT_ID('dbo.cms_settings','U') IS NULL
CREATE TABLE dbo.cms_settings(
 setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
 setting_value NVARCHAR(MAX) NULL,
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO
IF OBJECT_ID('dbo.cms_admin_users','U') IS NULL
CREATE TABLE dbo.cms_admin_users(
 id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 username VARCHAR(50) NOT NULL UNIQUE,
 account_id VARCHAR(10) NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role VARCHAR(40) NOT NULL DEFAULT 'superadmin',
 is_active BIT NOT NULL DEFAULT 1,
 last_login_at DATETIME2 NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO
IF OBJECT_ID('dbo.cms_news','U') IS NULL
CREATE TABLE dbo.cms_news(
 id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 title NVARCHAR(180) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 summary NVARCHAR(500) NULL,
 content NVARCHAR(MAX) NOT NULL,
 image NVARCHAR(255) NULL,
 category NVARCHAR(80) NULL,
 author_id BIGINT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 featured BIT NOT NULL DEFAULT 0,
 views BIGINT NOT NULL DEFAULT 0,
 published_at DATETIME2 NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF OBJECT_ID('dbo.cms_guides','U') IS NULL
CREATE TABLE dbo.cms_guides(
 id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 title NVARCHAR(180) NOT NULL,
 slug VARCHAR(160) NOT NULL,
 summary NVARCHAR(700) NULL,
 content_html NVARCHAR(MAX) NOT NULL,
 category NVARCHAR(60) NULL,
 author VARCHAR(64) NOT NULL,
 logo_url NVARCHAR(700) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 featured BIT NOT NULL DEFAULT 0,
 views INT NOT NULL DEFAULT 0,
 published_at DATETIME2 NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_cms_guides_slug' AND object_id=OBJECT_ID('dbo.cms_guides'))
CREATE UNIQUE INDEX UX_cms_guides_slug ON dbo.cms_guides(slug);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_cms_guides_status_date' AND object_id=OBJECT_ID('dbo.cms_guides'))
CREATE INDEX IX_cms_guides_status_date ON dbo.cms_guides(status,published_at,created_at);
GO
IF OBJECT_ID('dbo.cms_audit_log','U') IS NULL
CREATE TABLE dbo.cms_audit_log(
 id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 actor NVARCHAR(100) NULL,
 action VARCHAR(100) NOT NULL,
 target NVARCHAR(190) NULL,
 ip VARCHAR(45) NULL,
 context NVARCHAR(MAX) NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO
IF OBJECT_ID('dbo.cms_sessions','U') IS NULL
CREATE TABLE dbo.cms_sessions(
 id VARCHAR(128) NOT NULL PRIMARY KEY,
 admin_user_id BIGINT NULL,
 ip_hash CHAR(64) NULL,
 user_agent_hash CHAR(64) NULL,
 last_activity_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO
IF OBJECT_ID('dbo.cms_login_attempts','U') IS NULL
CREATE TABLE dbo.cms_login_attempts(
 id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 username VARCHAR(50) NULL,
 ip_hash CHAR(64) NOT NULL,
 successful BIT NOT NULL DEFAULT 0,
 attempted_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

GO
IF COL_LENGTH('dbo.cms_admin_users','account_id') IS NULL
    ALTER TABLE dbo.cms_admin_users ADD account_id VARCHAR(10) NULL;
GO
