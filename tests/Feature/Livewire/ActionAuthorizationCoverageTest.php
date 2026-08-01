<?php

/*
 * Regression guard for the Livewire authorization backfill (US-002..US-007).
 *
 * Every Livewire public method is directly invokable from the browser, so any method
 * that writes to the database is an authorization surface. This test reflects over the
 * whole of src/Livewire/ and fails -- naming the offending Class::method -- when a
 * mutating action method has no $this->authorize() call and no documented exemption.
 *
 * Scope: a method is checked when its body contains a persistence signal (see
 * PERSISTENCE_SIGNALS). Read-only methods -- the computed data providers this codebase
 * calls from render(), e.g. DealIndex::deals(), ChatIndex::headers() -- cannot bypass a
 * write guard, so requiring authorize() on them would mean allow-listing ~280 methods
 * and the list would stop meaning anything. The signal list is audited by the
 * "no persistence signal is missing" test below.
 *
 * This test performs static analysis only. It boots no component, authenticates no
 * user, and deliberately does not use Gate::before(fn () => true) -- there is no gate
 * to short-circuit, and the sibling deny/allow suites in Feature/Livewire/Authorization
 * must keep driving denial through the real policies.
 */

/**
 * Substrings that mean "this method persists something".
 *
 * Derived from the brace-matched body scan used to verify US-002, and re-audited by the
 * "flags any persistence signal missing" test below.
 *
 * The Service:: / Service-> entries matter as much as the Eloquent ones: several
 * components never touch a model directly and delegate the whole write to a service
 * (ChatShow::send calls app(ChatService::class)->sendAgentMessage(), the create/edit
 * pages call $this->personService->..., and so on). Without them those methods read as
 * non-mutating and their guards would not be covered by this test at all.
 */
const PERSISTENCE_SIGNALS = [
    // Eloquent / query-builder writes.
    '->save(', '->saveQuietly(', '->delete(', '->forceDelete(', '->update(',
    '->updateOrCreate(', '->forceFill(', '->create(', '::create(', '::updateOrCreate(',
    '::destroy(', '->attach(', '->detach(', '->sync(', '->syncWithoutDetaching(',
    '->restore(', '->increment(', '->decrement(', '->push(', '->firstOrCreate(',
    '->insert(', '->upsert(', '->associate(', '->dissociate(', '->saveMany(',

    // Writes delegated to a CRM service, either resolved from the container
    // (app(FooService::class)->bar()) or injected as an action-method argument
    // (public function cancel(EmailCampaignService $service)). Every one of the
    // twelve $service-> call sites in src/Livewire is a write.
    'Service::', 'Service->', '$service->',

    // Queued/synchronous job dispatch. Matches SomeJob::dispatch( and
    // ::dispatchSync( without catching Livewire's own $this->dispatch() events,
    // which are browser messages rather than side effects.
    '::dispatch',
];

/**
 * Livewire lifecycle hooks. Never user-invoked actions, so never an authorization
 * surface in their own right.
 */
const LIFECYCLE_METHODS = [
    'render', 'mount', 'mountCommon', 'boot', 'booted', 'hydrate', 'dehydrate',
    'updated', 'updating', 'rendering', 'rendered', 'exception',
];

/** Prefixes whose CamelCase suffix forms make a per-property lifecycle hook. */
const LIFECYCLE_PREFIXES = ['boot', 'hydrate', 'dehydrate', 'updated', 'updating'];

/**
 * Whole namespaces excluded from the guard.
 *
 * Auth/     -- pre-authentication (login, password reset). There is no authenticated
 *              user to authorize, so a policy check is meaningless.
 * Profile/  -- the signed-in user acting on their own account (password, 2FA, avatar,
 *              self-deletion). The subject IS the actor; ownership is the guard.
 * Portal/   -- the public customer portal. Guarded by signed URLs and the portal guard
 *              at the route layer, not by CRM policies.
 */
const SAFE_NAMESPACES = ['Auth/', 'Portal/', 'Profile/'];

/**
 * Deliberate, permanent exemptions.
 *
 * In-memory only -- these mutate a public array on the component (a repeater row, a
 * phone/email/address draft, a product line) and never touch the database. Persistence
 * happens later in the owning component's save(), which IS guarded. The
 * "stay free of persistence" test below re-asserts they still perform no writes, so
 * an entry here can never silently start covering a real database mutation.
 *
 * Delegates -- ProductCreate::createProduct() is a thin wrapper that immediately calls
 * $this->save(false); guarding both would double-authorize the same action.
 */
const SAFE_METHODS = [
    // In-memory repeater rows on the shared sub-components.
    'ModelPhones::add', 'ModelPhones::delete',
    'ModelEmails::add', 'ModelEmails::remove',
    'ModelAddresses::add', 'ModelAddresses::remove',
    'ModelProducts::add', 'ModelProducts::remove',
    'RelatedDeals::add', 'RelatedDeals::remove',

    // In-memory UI state toggles / draft rows on individual components.
    'QuoteSend::toggle',
    'SettingEdit::addPhone', 'SettingEdit::deletePhone',

    // In-memory draft rows contributed by the shared Has*Common traits.
    'HasPersonCommon::addPhone', 'HasPersonCommon::deletePhone',
    'HasPersonCommon::addEmail', 'HasPersonCommon::deleteEmail',
    'HasPersonCommon::addAddress', 'HasPersonCommon::deleteAddress',
    'HasOrganizationCommon::addPhone', 'HasOrganizationCommon::deletePhone',
    'HasOrganizationCommon::addEmail', 'HasOrganizationCommon::deleteEmail',
    'HasOrganizationCommon::addAddress', 'HasOrganizationCommon::deleteAddress',
    'HasUserCommon::addPhone', 'HasUserCommon::deletePhone',
    'HasUserCommon::addAddress', 'HasUserCommon::deleteAddress',
    'HasDealCommon::addProduct', 'HasDealCommon::deleteProduct',
    'HasCustomFieldCommon::removeOption',

    // Delegates to an already-guarded method on the same component.
    'ProductCreate::createProduct',
];

/**
 * SAFE_METHODS entries that legitimately contain a persistence signal because they
 * delegate to a guarded method, and so are exempt from the "stay free of persistence"
 * assertion.
 */
const SAFE_METHOD_DELEGATES = ['ProductCreate::createProduct'];

/**
 * Pre-existing gaps this story does NOT close.
 *
 * The US-002..US-007 backfill covered Deals, Leads, Quotes, Orders, Invoices,
 * PurchaseOrders, Deliveries, Products, Tasks, People, Organizations, the shared
 * related/sub-item components, Settings, Chat, imports, templates and the campaign
 * cancel paths. It never reached the Monitors or Teams domains, the EmailCampaign
 * create/edit pages, or the User create/edit/show/invite paths -- a gap US-005 already
 * recorded for UserCreate/UserEdit.
 *
 * This list is a ratchet, not an excuse: the "ratchet stays honest" test below fails if
 * an entry is guarded (remove it) or disappears (remove it). It can only shrink.
 */
const KNOWN_UNGUARDED = [
    'EmailCampaignCreate::save',
    'EmailCampaignEdit::save',
    'MonitorCreate::save',
    'MonitorEdit::save',
    'TeamCreate::save',
    'TeamEdit::save',
    'TeamShow::delete',
    'UserCreate::save',
    'UserEdit::save',
    'UserIndex::deleteInvitation',
    'UserIndex::resendInvitation',
    'UserInvite::save',
    'UserShow::delete',
];

/**
 * Guarded methods whose body contains no PERSISTENCE_SIGNALS entry.
 *
 * These are genuine authorization surfaces that the signal scan cannot see, because the
 * write happens behind an indirection this list deliberately does not try to model:
 *
 *   *Import::startImport  -- validates, seeds the component's chunk cursor and dispatches
 *                            a browser event; the rows are written by processNextChunk(),
 *                            which is separately guarded and does match a signal. Guarding
 *                            the entry point too is correct (every public Livewire method
 *                            is independently invokable), it just writes nothing itself.
 *   TemplateSettings::save -- persists through the SettingService singleton resolved as
 *                            app('laravel-crm.settings')->set(), and `->set(` is far too
 *                            generic to add to PERSISTENCE_SIGNALS.
 *
 * Without this pin, deleting the authorize() call from one of these four would leave it
 * both unguarded and undetected, and the suite would still pass. The test below therefore
 * asserts both directions: every entry is still guarded, and the computed set of
 * guarded-but-unsignalled methods is exactly this list -- so a new guard on a
 * non-mutating method has to be pinned here rather than silently going uncovered.
 */
const GUARDED_WITHOUT_PERSISTENCE_SIGNAL = [
    'OrganizationImport::startImport',
    'PersonImport::startImport',
    'TemplateSettings::save',
    'UserImport::startImport',
];

/**
 * Reflect over src/Livewire and describe every public action method.
 *
 * Methods are keyed by the short name of the class *or trait* that physically declares
 * them. ReflectionMethod::getDeclaringClass() reports the *using* class for a trait
 * method (PHP treats `use SomeTrait` as compile-time copy-paste), so getFileName() is
 * the only reliable discriminator -- it also collapses a trait method shared by five
 * components into a single entry, and is what makes SAFE_METHODS entries such as
 * HasPersonCommon::addPhone resolvable.
 *
 * The same getFileName() check excludes everything inherited from Livewire\Component,
 * WithPagination and Mary\Traits\Toast, since those declare in vendor/.
 *
 * @return array<string, array{guarded: bool, mutates: bool, body: string, relative: string}>
 */
function actionAuthzMethods(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $root = dirname(__DIR__, 3);
    $livewireDir = $root.'/src/Livewire';

    $classes = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($livewireDir));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)) {
            continue;
        }

        if (! preg_match('/^(?:abstract\s+|final\s+)?class\s+(\w+)/m', $source, $class)) {
            continue;
        }

        $classes[] = trim($namespace[1]).'\\'.$class[1];
    }

    sort($classes);

    $isLifecycle = function (string $name): bool {
        if (in_array($name, LIFECYCLE_METHODS, true)) {
            return true;
        }

        foreach (LIFECYCLE_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)
                && strlen($name) > strlen($prefix)
                && ctype_upper($name[strlen($prefix)])) {
                return true;
            }
        }

        return preg_match('/^get.+Property$/', $name) === 1;
    };

    $methods = [];

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            continue;
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            $file = $method->getFileName();

            // Inherited from the framework (Livewire\Component, WithPagination, Toast).
            if (! $file || ! str_starts_with($file, $livewireDir)) {
                continue;
            }

            if ($isLifecycle($method->getName())) {
                continue;
            }

            $relative = str_replace($livewireDir.'/', '', $file);

            $exemptNamespace = false;

            foreach (SAFE_NAMESPACES as $namespace) {
                if (str_starts_with($relative, $namespace)) {
                    $exemptNamespace = true;

                    break;
                }
            }

            // Skip this method only. `continue 3` here would target the outer class
            // loop and silently drop the rest of the class's methods the moment a
            // trait declared under one of these namespaces is used elsewhere.
            if ($exemptNamespace) {
                continue;
            }

            $declaringSource = file_get_contents($file);
            preg_match('/^(?:abstract\s+|final\s+)?(?:class|trait)\s+(\w+)/m', $declaringSource, $owner);

            $body = implode("\n", array_slice(
                file($file),
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1
            ));

            $mutates = false;

            foreach (PERSISTENCE_SIGNALS as $signal) {
                if (str_contains($body, $signal)) {
                    $mutates = true;

                    break;
                }
            }

            $methods[($owner[1] ?? $reflection->getShortName()).'::'.$method->getName()] = [
                'guarded' => str_contains($body, '$this->authorize('),
                'mutates' => $mutates,
                'body' => $body,
                'relative' => $relative,
            ];
        }
    }

    ksort($methods);

    return $cache = $methods;
}

/** Render an offender list so a failure diff names the exact Class::method. */
function actionAuthzReport(array $offenders): string
{
    return $offenders === [] ? '' : "\n  - ".implode("\n  - ", $offenders)."\n";
}

it('scans a meaningful number of Livewire action methods', function () {
    // A guard that silently examined nothing would pass every other test in this file.
    $methods = actionAuthzMethods();
    $mutating = array_filter($methods, fn (array $m) => $m['mutates']);

    expect(count($methods))->toBeGreaterThan(300)
        ->and(count($mutating))->toBeGreaterThan(150)
        ->and(array_filter($mutating, fn (array $m) => $m['guarded']))->not->toBeEmpty();
});

it('requires an authorize() call on every mutating Livewire action method', function () {
    $offenders = [];

    foreach (actionAuthzMethods() as $key => $method) {
        if (! $method['mutates'] || $method['guarded']) {
            continue;
        }

        if (in_array($key, SAFE_METHODS, true) || in_array($key, KNOWN_UNGUARDED, true)) {
            continue;
        }

        $offenders[] = $key.'   ('.$method['relative'].')';
    }

    expect(actionAuthzReport($offenders))->toBe(
        '',
        'These Livewire methods persist data but have no $this->authorize() call. '
        .'Add a guard, or -- if the write is genuinely safe -- add the method to SAFE_METHODS with a rationale.'
    );
});

it('keeps every SAFE_METHODS entry resolvable and free of persistence', function () {
    $methods = actionAuthzMethods();
    $missing = [];
    $nowMutating = [];

    foreach (SAFE_METHODS as $key) {
        if (! isset($methods[$key])) {
            $missing[] = $key;

            continue;
        }

        if ($methods[$key]['mutates'] && ! in_array($key, SAFE_METHOD_DELEGATES, true)) {
            $nowMutating[] = $key.'   ('.$methods[$key]['relative'].')';
        }
    }

    expect(actionAuthzReport($missing))->toBe(
        '',
        'These SAFE_METHODS entries no longer resolve to a Livewire method. Remove the stale entries.'
    );

    expect(actionAuthzReport($nowMutating))->toBe(
        '',
        'These SAFE_METHODS entries were exempted as in-memory only but now persist data. '
        .'Add an authorize() guard and remove them from SAFE_METHODS.'
    );
});

it('keeps guarded methods guarded even when no persistence signal detects them', function () {
    $methods = actionAuthzMethods();

    // Direction 1: nobody may quietly remove one of these guards. The mutating check
    // cannot catch it, because a method with neither a signal nor an authorize() call
    // simply drops out of scope.
    $unguarded = [];

    foreach (GUARDED_WITHOUT_PERSISTENCE_SIGNAL as $key) {
        if (! isset($methods[$key])) {
            $unguarded[] = $key.'   (method no longer exists)';

            continue;
        }

        if (! $methods[$key]['guarded']) {
            $unguarded[] = $key.'   ('.$methods[$key]['relative'].')';
        }
    }

    expect(actionAuthzReport($unguarded))->toBe(
        '',
        'These methods are authorization surfaces that PERSISTENCE_SIGNALS cannot detect, '
        .'so the mutating check will not notice their guard going missing. Restore the '
        .'$this->authorize() call.'
    );

    // Direction 2: the pin must stay exhaustive, or a future guard on a non-mutating
    // method would be removable without any test noticing.
    $unpinned = [];

    foreach ($methods as $key => $method) {
        if ($method['guarded']
            && ! $method['mutates']
            && ! in_array($key, GUARDED_WITHOUT_PERSISTENCE_SIGNAL, true)) {
            $unpinned[] = $key.'   ('.$method['relative'].')';
        }
    }

    expect(actionAuthzReport($unpinned))->toBe(
        '',
        'These methods are guarded but match no PERSISTENCE_SIGNALS entry, so removing '
        .'their guard would go undetected. Add them to GUARDED_WITHOUT_PERSISTENCE_SIGNAL '
        .'(or add the write they perform to PERSISTENCE_SIGNALS).'
    );
});

it('keeps the KNOWN_UNGUARDED ratchet honest', function () {
    $methods = actionAuthzMethods();
    $stale = [];

    foreach (KNOWN_UNGUARDED as $key) {
        if (! isset($methods[$key])) {
            $stale[] = $key.'   (method no longer exists)';

            continue;
        }

        if ($methods[$key]['guarded']) {
            $stale[] = $key.'   (now guarded)';
        }
    }

    expect(actionAuthzReport($stale))->toBe(
        '',
        'KNOWN_UNGUARDED lists pre-existing gaps and may only shrink. '
        .'Remove these entries now that they are guarded or gone.'
    );
});

it('does not short-circuit the Gate anywhere in the authorization suites', function () {
    $files = array_merge(
        [__FILE__],
        glob(__DIR__.'/Authorization/*.php') ?: []
    );

    // Compare executable tokens only. A plain str_contains() would match this very
    // test's own docblock and failure message, and any sibling that merely documents
    // why it avoids the hook.
    $codeOnly = function (string $file): string {
        $code = '';

        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_INLINE_HTML], true)) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        return $code;
    };

    $offenders = [];

    foreach ($files as $file) {
        if (str_contains($codeOnly($file), 'Gate::before')) {
            $offenders[] = basename($file);
        }
    }

    expect(count($files))->toBeGreaterThan(20)
        ->and(actionAuthzReport($offenders))->toBe(
            '',
            'Gate::before(fn () => true) makes every policy pass, so an authorization test using it '
            .'proves nothing. Grant explicit permissions with actingAsUserWithPermissions() instead.'
        );
});

it('flags any persistence signal missing from the detection list', function () {
    // Widen the net over every method PERSISTENCE_SIGNALS considers non-mutating.
    // Anything that looks like it could write is a candidate false negative -- and a
    // false negative silently makes this whole file vacuous for that method, because a
    // guard nobody detects is a guard nobody can miss removing.
    //
    // Guarded methods are deliberately NOT skipped here. Skipping them is exactly what
    // hid ChatShow::send (which writes only through app(ChatService::class)) until a
    // mutation test caught it.
    $suspicious = [
        'DB::', 'Storage::', 'Mail::', 'Notification::', 'Artisan::',
        'Repository::', 'Repository->',
        '->touch(', '->truncate(', '->fill(', '->setAttribute(',
    ];

    $knownReads = [
        'FileItem::download' => 'Storage::disk()->download() -- serves a file, writes nothing',
        'HasPersonSuggest::searchPeople' => 'DB::raw() inside a search SELECT',
        'UserIndex::users' => 'DB::table() inside the team-scoping whereExists SELECT',
    ];

    $unexpected = [];

    foreach (actionAuthzMethods() as $key => $method) {
        if ($method['mutates'] || isset($knownReads[$key])) {
            continue;
        }

        foreach ($suspicious as $signal) {
            if (str_contains($method['body'], $signal)) {
                $unexpected[] = $key.'   (matched '.$signal.')';

                break;
            }
        }
    }

    expect(array_keys($knownReads))->each->toBeIn(array_keys(actionAuthzMethods()));

    expect(actionAuthzReport($unexpected))->toBe(
        '',
        'These methods call something write-adjacent but match no PERSISTENCE_SIGNALS entry. '
        .'Confirm each is a read; if any writes, add its signal to PERSISTENCE_SIGNALS.'
    );
});
