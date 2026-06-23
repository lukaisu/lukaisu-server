-- Migration: Migrate dictionary URL settings to dedicated columns
-- This migration extracts popup settings from URLs and replaces ### with lukaisu_term

-- Step 1: Detect asterisk prefix and lukaisu_popup param, set popup columns

-- Dictionary 1: asterisk prefix
UPDATE `languages`
SET `LgDict1PopUp` = 1
WHERE `LgDict1URI` LIKE '*%';

-- Dictionary 1: lukaisu_popup param
UPDATE `languages`
SET `LgDict1PopUp` = 1
WHERE `LgDict1URI` LIKE '%lukaisu_popup=%'
  AND `LgDict1PopUp` = 0;

-- Dictionary 2: asterisk prefix
UPDATE `languages`
SET `LgDict2PopUp` = 1
WHERE `LgDict2URI` LIKE '*%';

-- Dictionary 2: lukaisu_popup param
UPDATE `languages`
SET `LgDict2PopUp` = 1
WHERE `LgDict2URI` LIKE '%lukaisu_popup=%'
  AND `LgDict2PopUp` = 0;

-- Translator: asterisk prefix
UPDATE `languages`
SET `LgGoogleTranslatePopUp` = 1
WHERE `LgGoogleTranslateURI` LIKE '*%';

-- Translator: lukaisu_popup param
UPDATE `languages`
SET `LgGoogleTranslatePopUp` = 1
WHERE `LgGoogleTranslateURI` LIKE '%lukaisu_popup=%'
  AND `LgGoogleTranslatePopUp` = 0;

-- Step 2: Strip asterisk prefix from URLs

UPDATE `languages`
SET `LgDict1URI` = SUBSTRING(`LgDict1URI`, 2)
WHERE `LgDict1URI` LIKE '*%';

UPDATE `languages`
SET `LgDict2URI` = SUBSTRING(`LgDict2URI`, 2)
WHERE `LgDict2URI` LIKE '*%';

UPDATE `languages`
SET `LgGoogleTranslateURI` = SUBSTRING(`LgGoogleTranslateURI`, 2)
WHERE `LgGoogleTranslateURI` LIKE '*%';

-- Step 3: Replace ### with lukaisu_term in all dictionary URLs

UPDATE `languages`
SET `LgDict1URI` = REPLACE(`LgDict1URI`, '###', 'lukaisu_term')
WHERE `LgDict1URI` LIKE '%###%';

UPDATE `languages`
SET `LgDict2URI` = REPLACE(`LgDict2URI`, '###', 'lukaisu_term')
WHERE `LgDict2URI` LIKE '%###%';

UPDATE `languages`
SET `LgGoogleTranslateURI` = REPLACE(`LgGoogleTranslateURI`, '###', 'lukaisu_term')
WHERE `LgGoogleTranslateURI` LIKE '%###%';

-- Step 4: Extract source language from translator URLs
-- Google Translate uses sl= parameter
-- LibreTranslate uses source= parameter

-- Google Translate sl= parameter
UPDATE `languages`
SET `LgSourceLang` = SUBSTRING_INDEX(
    SUBSTRING_INDEX(`LgGoogleTranslateURI`, 'sl=', -1),
    '&', 1
)
WHERE `LgGoogleTranslateURI` LIKE '%sl=%'
  AND `LgSourceLang` IS NULL;

-- LibreTranslate source= parameter
UPDATE `languages`
SET `LgSourceLang` = SUBSTRING_INDEX(
    SUBSTRING_INDEX(`LgGoogleTranslateURI`, 'source=', -1),
    '&', 1
)
WHERE `LgGoogleTranslateURI` LIKE '%source=%'
  AND `LgGoogleTranslateURI` LIKE '%lukaisu_translator=libretranslate%'
  AND `LgSourceLang` IS NULL;

-- Google Translate tl= parameter (target language)
UPDATE `languages`
SET `LgTargetLang` = SUBSTRING_INDEX(
    SUBSTRING_INDEX(`LgGoogleTranslateURI`, 'tl=', -1),
    '&', 1
)
WHERE `LgGoogleTranslateURI` LIKE '%tl=%'
  AND `LgTargetLang` IS NULL;

-- LibreTranslate target= parameter
UPDATE `languages`
SET `LgTargetLang` = SUBSTRING_INDEX(
    SUBSTRING_INDEX(`LgGoogleTranslateURI`, 'target=', -1),
    '&', 1
)
WHERE `LgGoogleTranslateURI` LIKE '%target=%'
  AND `LgGoogleTranslateURI` LIKE '%lukaisu_translator=libretranslate%'
  AND `LgTargetLang` IS NULL;

-- Note: lukaisu_popup parameter removal is handled by the application
-- since SQL string manipulation for query param removal is complex.
-- The application will ignore this parameter going forward.
