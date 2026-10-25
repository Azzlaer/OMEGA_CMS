-- OMEGA CMS - Guides Center
-- Safe migration for existing CMS installations. Does not alter MuOnline97 game tables.
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
