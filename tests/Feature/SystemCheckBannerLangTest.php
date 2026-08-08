<?php

/**
 * Locks the lang keys the system check banner renders.
 *
 * en is the only locale asserted on: en_au and en_gb are override-only files
 * that fall back to en, and fa is left for a translator.
 */
function systemCheckBannerKeys(): array
{
    return [
        'important',
        'dismiss',
        'system_check_upgrade_required',
        'system_check_upgrade_guide',
        'system_check_update_available',
        'system_check_view_version_details',
        'system_check_update_now',
        'system_check_db_update_required',
        'system_check_db_update_command',
        'system_check_update_database',
    ];
}

function systemCheckBannerLangPath(): string
{
    return __DIR__.'/../../resources/lang/en/lang.php';
}

test('every system check banner key resolves in en', function () {
    $lang = require systemCheckBannerLangPath();

    expect(systemCheckBannerKeys())->toHaveCount(10);

    foreach (systemCheckBannerKeys() as $key) {
        expect($lang)->toHaveKey($key);
        expect($lang[$key])->toBeString();
        expect(trim($lang[$key]))->not->toBe('');
    }
});

test('no system check banner key is declared twice in en', function () {
    // A duplicate key is silently resolved last-write-wins by PHP, so the
    // returned array can never reveal one — count in the raw source instead.
    $source = file_get_contents(systemCheckBannerLangPath());

    foreach (systemCheckBannerKeys() as $key) {
        expect(substr_count($source, "'{$key}' => "))->toBe(
            1,
            "Expected exactly one declaration of '{$key}' in en/lang.php."
        );
    }
});

test('system check banner sentences carry their links as placeholders', function () {
    $lang = require systemCheckBannerLangPath();

    // Whole sentences with placeholders — not fragments concatenated in Blade —
    // so translators keep control of word order for RTL locales such as fa.
    expect($lang['system_check_upgrade_required'])->toContain(':guide');
    expect($lang['system_check_update_available'])->toContain(':details');
    expect($lang['system_check_update_available'])->toContain(':update');
    expect($lang['system_check_view_version_details'])->toContain(':version');
    expect($lang['system_check_db_update_required'])->toContain(':updates_page');
    // The command an operator actually has to type, not just a link to a page
    // about it — neither the banner nor the updates page used to say it.
    expect($lang['system_check_db_update_required'])->toContain(':command');
    expect($lang['system_check_db_update_command'])->toBe('php artisan laravelcrm:update');
});

test('system check banner placeholders substitute through the translator', function () {
    expect(__('laravel-crm::lang.system_check_upgrade_required', ['guide' => 'UPGRADE_GUIDE']))
        ->toContain('UPGRADE_GUIDE')
        ->not->toContain(':guide');

    expect(__('laravel-crm::lang.system_check_view_version_details', ['version' => '2.4.0']))
        ->toContain('2.4.0')
        ->not->toContain(':version');

    expect(__('laravel-crm::lang.system_check_update_available', [
        'details' => 'DETAILS_LINK',
        'update' => 'UPDATE_LINK',
    ]))->toContain('DETAILS_LINK')
        ->toContain('UPDATE_LINK')
        ->not->toContain(':details')
        ->not->toContain(':update');

    expect(__('laravel-crm::lang.system_check_db_update_required', [
        'updates_page' => 'UPDATE_DB_LINK',
        'command' => 'UPDATE_DB_COMMAND',
    ]))->toContain('UPDATE_DB_LINK')
        ->toContain('UPDATE_DB_COMMAND')
        ->not->toContain(':updates_page')
        ->not->toContain(':command');
});

test('the link label keys are plain strings with no placeholders', function () {
    $lang = require systemCheckBannerLangPath();

    foreach (['important', 'dismiss', 'system_check_upgrade_guide', 'system_check_update_now', 'system_check_update_database'] as $key) {
        expect($lang[$key])->not->toMatch('/:[a-z_]+/');
    }
});

test('substituting the update command does not corrupt it', function () {
    // Laravel replaces placeholders longest key first, so :command runs after
    // any shorter key. If the link placeholder were :update, this substitution
    // would rewrite the ":update" *inside* "php artisan laravelcrm:update" and
    // hand the operator a command that does not exist.
    $rendered = __('laravel-crm::lang.system_check_db_update_required', [
        'command' => __('laravel-crm::lang.system_check_db_update_command'),
        'updates_page' => 'UPDATES_PAGE_LINK',
    ]);

    expect($rendered)->toContain('php artisan laravelcrm:update')
        ->toContain('UPDATES_PAGE_LINK');
});
