<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) { $failures[] = $message; }
};

$manifest = json_decode((string) file_get_contents($root.'/extension.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($manifest['name'] ?? null) === 'Titan Nova', 'Manifest name must be Titan Nova');
$assert(($manifest['folder'] ?? null) === 'TitanNova', 'Manifest folder must be TitanNova');
$assert(($manifest['provider'] ?? null) === 'App\\Extensions\\TitanNova\\System\\TitanNovaServiceProvider', 'Provider mismatch');
$assert(($manifest['register_key'] ?? null) === 'titan-nova', 'Register key mismatch');

$required = [
    'System/TitanNovaServiceProvider.php',
    'System/Models/TitanNovaAgent.php',
    'System/Models/TitanNovaTask.php',
    'System/Services/TaskCreationService.php',
    'System/Console/Commands/CreateAgentTasksCommand.php',
    'System/Console/Commands/RunScheduledTasksCommand.php',
    'System/Http/Controllers/TitanNovaController.php',
    'resources/views/tasks/index.blade.php',
    'database/migrations/2026_01_26_110803_create_ext_titan_nova_agents_table.php',
    'database/migrations/2026_02_03_122314_create_ext_titan_nova_tasks_table.php',
];
foreach ($required as $file) { $assert(is_file($root.'/'.$file), 'Missing '.$file); }

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (! $file->isFile()) { continue; }
    $path = $file->getPathname();
    $relative = substr($path, strlen($root) + 1);
    $assert(! preg_match('/blogpilot|wordpress|post/i', $relative), 'Old filename remains: '.$relative);
    if (in_array(strtolower($file->getExtension()), ['php','json','md'], true) || str_ends_with($relative, '.blade.php')) {
        $content = (string) file_get_contents($path);
        if ($relative !== 'tests/titan_nova_verify.php') {
            $oldPattern = '/'.'Blog'.'Pilot|'.'blog'.'pilot|'.'Word'.'Press|'.'word'.'press/';
            $assert(! preg_match($oldPattern, $content), 'Old identifier remains in '.$relative);
        }
    }
    if ($file->getExtension() === 'php') {
        exec('php -l '.escapeshellarg($path).' 2>&1', $output, $code);
        $assert($code === 0, 'PHP lint failed: '.$relative.' '.implode("\n", $output));
        $output = [];
    }
}

$provider = (string) file_get_contents($root.'/System/TitanNovaServiceProvider.php');
$assert(str_contains($provider, "return 'titan-nova';"), 'Provider register key missing');
$assert(str_contains($provider, "titan-nova:create-tasks"), 'Task creation command missing');
$assert(str_contains($provider, "titan-nova:run-scheduled-tasks"), 'Scheduled task command missing');
$assert(str_contains($provider, "titan-nova/channel-webhook"), 'Generic channel webhook missing');

$taskMigration = (string) file_get_contents($root.'/database/migrations/2026_02_03_122314_create_ext_titan_nova_tasks_table.php');
foreach (['ext_titan_nova_tasks', "'channel'", "'channel_payload'", "'channel_receipt'", "'completed'"] as $needle) {
    $assert(str_contains($taskMigration, $needle), 'Task migration missing '.$needle);
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}

echo "Titan Nova verification passed\n";
