<?php echo "<?php\n"; ?>
if (!defined('HORDE_BASE')) define('HORDE_BASE', '<?= $hordeBaseDir ?>');
if (!defined('HORDE_CONFIG_BASE')) define('HORDE_CONFIG_BASE', '<?= $configDir ?>');
if (!defined('<?= $appNameUpper ?>_TEMPLATES')) define('<?= $appNameUpper ?>_TEMPLATES', '<?= $templatesDir ?>');
<?php if ($autoloadExtraFilePath !== null): ?>

require_once('<?= $autoloadExtraFilePath ?>')
<?php endif; ?>
