-- OMEGA CMS - Google & Monetization Center PRO
-- Safe CMS-only migration. Does not modify MuOnline game tables.
IF OBJECT_ID('dbo.cms_monetization_settings','U') IS NULL
CREATE TABLE dbo.cms_monetization_settings(
 setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
 setting_value NVARCHAR(MAX) NULL,
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF OBJECT_ID('dbo.cms_ad_placements','U') IS NULL
CREATE TABLE dbo.cms_ad_placements(
 id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 placement_key VARCHAR(80) NOT NULL,
 name NVARCHAR(120) NOT NULL,
 provider VARCHAR(30) NOT NULL DEFAULT 'house',
 enabled BIT NOT NULL DEFAULT 1,
 audience VARCHAR(20) NOT NULL DEFAULT 'all',
 device VARCHAR(20) NOT NULL DEFAULT 'all',
 path_rule NVARCHAR(700) NULL,
 adsense_slot VARCHAR(32) NULL,
 ad_format VARCHAR(30) NULL,
 image_url NVARCHAR(700) NULL,
 target_url NVARCHAR(700) NULL,
 label_text NVARCHAR(180) NULL,
 html_code NVARCHAR(MAX) NULL,
 sort_order INT NOT NULL DEFAULT 100,
 starts_at DATETIME2 NULL,
 ends_at DATETIME2 NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF NOT EXISTS (
 SELECT 1 FROM sys.indexes
 WHERE name='IX_cms_ad_placements_key_active'
 AND object_id=OBJECT_ID('dbo.cms_ad_placements')
)
CREATE INDEX IX_cms_ad_placements_key_active
ON dbo.cms_ad_placements(placement_key,enabled,sort_order);
GO