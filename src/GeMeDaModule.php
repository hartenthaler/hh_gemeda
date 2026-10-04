<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\View;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchCriteria;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure\HttpGeMeDaApiClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure\GeMeDaLinkReader;

use function file_exists;
use function preg_match;
use function trim;

class GeMeDaModule extends AbstractModule implements ModuleConfigInterface, ModuleCustomInterface, ModuleTabInterface
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;
    use ModuleTabTrait;

    private const MODULE_NAME = 'hh_gemeda';
    private const GITHUB_USER = 'hartenthaler';
    private const PREF_API_BASE_URL = 'api_base_url';
    private const PREF_SERVICE_KEY = 'service_key';
    private const PREF_CONTRIBUTOR_PEPPER = 'contributor_pepper';
    private const PREF_AUTHORIZED_USERS = 'authorized_users';
    private const PREF_EXID_TAG = 'exid_tag';
    private const DEFAULT_API_BASE_URL = 'https://api.gemeda.rpi.digital';
    public const TAG_EXID = 'EXID';
    public const TAG_LEGACY_EXID = '_EXID';

    public function title(): string
    {
        return I18N::translate('GeMeDa');
    }

    public function description(): string
    {
        return I18N::translate('Displays and manages GeMeDa identity links for individuals.');
    }

    public function customModuleAuthorName(): string
    {
        return 'Hermann Hartenthaler';
    }

    public function customModuleVersion(): string
    {
        return trim((string) file_get_contents(__DIR__ . '/../version.txt'));
    }

    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::GITHUB_USER . '/' . self::MODULE_NAME . '/main/version.txt';
    }

    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/' . self::GITHUB_USER . '/' . self::MODULE_NAME;
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /** {@inheritdoc} */
    public function boot(): void
    {
        View::registerNamespace(
            $this->name(),
            strtr($this->resourcesFolder() . 'views' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '/'),
        );
    }

    public function defaultTabOrder(): int
    {
        return 35;
    }

    public function hasTabContent(Individual $individual): bool
    {
        return true;
    }

    public function isGrayedOut(Individual $individual): bool
    {
        return (new GeMeDaLinkReader())->read($individual) === [];
    }

    /** {@inheritdoc} */
    public function canLoadAjax(): bool
    {
        // The first implementation only reads local GEDCOM data. Keep the tab
        // server-rendered until the GeMeDa API integration is available.
        return false;
    }

    public function getTabContent(Individual $individual): string
    {
        return view($this->name() . '::tab', [
            'individual' => $individual,
            'links' => (new GeMeDaLinkReader())->read($individual),
            'api_configured' => $this->apiConfigured(),
            'search_url' => route('module', [
                'module' => $this->name(),
                'action' => 'Search',
                'tree' => $individual->tree()->name(),
                'xref' => $individual->xref(),
            ]),
        ]);
    }

    /** Search the configured GeMeDa endpoint for the current individual. */
    public function getSearchAction(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->searchContext($request);
        $criteria = $this->criteriaForIndividual($context['individual']);

        return $this->searchResponse($context, $criteria, [], null, false);
    }

    /** Add the selected GeMeDa result as an EXID block to the individual. */
    public function postSearchAction(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->searchContext($request);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $operation = (string) ($body['operation'] ?? 'search');
        if ($operation === 'search') {
            $criteria = $this->criteriaFromBody($body, $this->criteriaForIndividual($context['individual']));
            $results = [];
            $error = null;
            if (!$this->apiConfigured()) {
                $error = I18N::translate('The GeMeDa API is not configured. Ask an administrator to enter the API URL.');
            } else {
                try {
                    $results = $this->apiClient()->search($criteria);
                } catch (\Throwable $exception) {
                    $error = $exception->getMessage();
                }
            }

            return $this->searchResponse($context, $criteria, $results, $error, true);
        }

        if (!$context['individual']->canEdit()) {
            FlashMessages::addMessage(I18N::translate('You are not authorized to modify this individual.'), 'danger');
            return redirect($context['individual']->url());
        }

        $hash = trim((string) ($body['person_hash'] ?? ''));
        $name = trim((string) ($body['person_name'] ?? $hash));
        if ($hash === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{1,199}$/', $hash) !== 1) {
            FlashMessages::addMessage(I18N::translate('The GeMeDa person identifier is invalid.'), 'danger');
            return redirect($this->searchUrl($context['tree']->name(), $context['individual']->xref()));
        }

        $gedcom = $context['individual']->gedcom();
        if (preg_match('/^1 (?:EXID|_EXID) ' . preg_quote($hash, '/') . '\R(?:2 TYPE gemeda\R)?/mi', $gedcom) === 1) {
            FlashMessages::addMessage(I18N::translate('This GeMeDa identifier is already stored.'), 'warning');
            return redirect($this->searchUrl($context['tree']->name(), $context['individual']->xref()));
        }

        $tag = $this->preferredExidTag();
        $gedcom = rtrim($gedcom) . "\n1 {$tag} {$hash}\n2 TYPE gemeda\n";
        $context['individual']->updateRecord($gedcom, false);
        FlashMessages::addMessage(I18N::translate('The GeMeDa identifier for %s was added.', $name), 'success');

        return redirect($context['individual']->url());
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'api_base_url' => $this->getPreference(self::PREF_API_BASE_URL, self::DEFAULT_API_BASE_URL),
            'service_key_configured' => $this->getPreference(self::PREF_SERVICE_KEY, '') !== '',
            'contributor_pepper_configured' => $this->getPreference(self::PREF_CONTRIBUTOR_PEPPER, '') !== '',
            'users' => $this->availableUsers(),
            'authorized_user_ids' => $this->authorizedUserIds(),
            'selected_exid_tag' => $this->preferredExidTag(),
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $this->setPreference(self::PREF_API_BASE_URL, trim((string) ($body['api_base_url'] ?? self::DEFAULT_API_BASE_URL)));

        $exid_tag = trim((string) ($body['exid_tag'] ?? self::TAG_LEGACY_EXID));
        if (in_array($exid_tag, [self::TAG_EXID, self::TAG_LEGACY_EXID], true)) {
            $this->setPreference(self::PREF_EXID_TAG, $exid_tag);
        }

        $selected_user_ids = $body['authorized_user_ids'] ?? [];
        if (!is_array($selected_user_ids)) {
            $selected_user_ids = [];
        }

        $available_user_ids = array_keys($this->availableUsers());
        $selected_user_ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $user_id): int => (int) $user_id, $selected_user_ids),
            static fn (int $user_id): bool => $user_id > 0 && in_array($user_id, $available_user_ids, true),
        )));
        sort($selected_user_ids, SORT_NUMERIC);
        $this->setPreference(self::PREF_AUTHORIZED_USERS, implode(',', $selected_user_ids));

        foreach ([self::PREF_SERVICE_KEY => 'service_key', self::PREF_CONTRIBUTOR_PEPPER => 'contributor_pepper'] as $preference => $field) {
            $value = trim((string) ($body[$field] ?? ''));
            if ($value !== '') {
                $this->setPreference($preference, $value);
            }
        }

        FlashMessages::addMessage(I18N::translate('The GeMeDa settings have been updated.'), 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * Return the tag to use when the future write-enabled GeMeDa integration
     * creates a new external identifier.
     */
    public function preferredExidTag(): string
    {
        $tag = $this->getPreference(self::PREF_EXID_TAG, self::TAG_LEGACY_EXID);

        return in_array($tag, [self::TAG_EXID, self::TAG_LEGACY_EXID], true) ? $tag : self::TAG_LEGACY_EXID;
    }

    private function apiConfigured(): bool
    {
        // Person search is read-only. A service key is only required by the
        // future claim-writing workflow and must not hide the search button.
        return trim((string) $this->getPreference(self::PREF_API_BASE_URL, self::DEFAULT_API_BASE_URL)) !== '';
    }

    private function apiClient(): HttpGeMeDaApiClient
    {
        return new HttpGeMeDaApiClient(
            (string) $this->getPreference(self::PREF_API_BASE_URL, self::DEFAULT_API_BASE_URL),
            (string) $this->getPreference(self::PREF_SERVICE_KEY, ''),
        );
    }

    /** @return array{tree:\Fisharebest\Webtrees\Tree,individual:Individual} */
    private function searchContext(ServerRequestInterface $request): array
    {
        $params = $request->getAttributes() + $request->getQueryParams();
        $treeParameter = $params['tree'] ?? null;
        $tree = $treeParameter instanceof \Fisharebest\Webtrees\Tree
            ? $treeParameter
            : Registry::treeFactory()->make((string) $treeParameter);
        $xref = (string) ($params['xref'] ?? '');
        $individual = Registry::individualFactory()->make($xref, $tree);
        if (!$individual instanceof Individual) {
            throw new \RuntimeException('The individual could not be found.');
        }

        Auth::checkIndividualAccess($individual, false);

        return ['tree' => $tree, 'individual' => $individual];
    }

    private function searchUrl(string $tree, string $xref): string
    {
        return route('module', ['module' => $this->name(), 'action' => 'Search', 'tree' => $tree, 'xref' => $xref]);
    }

    /** @param array<string,mixed> $body */
    private function criteriaFromBody(array $body, GeMeDaSearchCriteria $fallback): GeMeDaSearchCriteria
    {
        return new GeMeDaSearchCriteria(
            trim((string) ($body['given_name'] ?? $fallback->givenName)),
            trim((string) ($body['surname'] ?? $fallback->surname)),
            trim((string) ($body['place'] ?? $fallback->place)),
            trim((string) ($body['birth_date'] ?? $fallback->birthDate)),
            trim((string) ($body['death_date'] ?? $fallback->deathDate)),
            20,
        );
    }

    /** @param array{tree:\Fisharebest\Webtrees\Tree,individual:Individual} $context @param list<GeMeDaSearchResult> $results */
    private function searchResponse(array $context, GeMeDaSearchCriteria $criteria, array $results, ?string $error, bool $searched): ResponseInterface
    {
        return $this->viewResponse($this->name() . '::search', [
            'title' => I18N::translate('Search GeMeDa for %s', trim(strip_tags($context['individual']->fullName()))),
            'individual' => $context['individual'],
            'tree' => $context['tree'],
            'criteria' => $criteria,
            'results' => $results,
            'error' => $error,
            'searched' => $searched,
            'can_edit' => $context['individual']->canEdit(),
            'search_url' => $this->searchUrl($context['tree']->name(), $context['individual']->xref()),
        ]);
    }

    private function criteriaForIndividual(Individual $individual): GeMeDaSearchCriteria
    {
        $gedcom = $individual->gedcom();
        $given = '';
        $surname = '';
        if (preg_match('/^1 NAME\s+([^\/\r\n]*)\/?([^\/\r\n]*)\/?/mi', $gedcom, $name) === 1) {
            $given = trim((string) ($name[1] ?? ''));
            $surname = trim((string) ($name[2] ?? ''));
        }
        $places = [];
        if (preg_match_all('/^2 PLAC\s+(.+)$/mi', $gedcom, $matches) > 0) {
            $places = array_values(array_filter(array_map('trim', $matches[1])));
        }
        return new GeMeDaSearchCriteria($given, $surname, (string) ($places[0] ?? ''), '', '', 20);
    }

    private function canCreateClaims(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return in_array(Auth::user()->id(), $this->authorizedUserIds(), true);
    }

    /**
     * @return array<int,int>
     */
    private function authorizedUserIds(): array
    {
        $ids = array_map('intval', explode(',', (string) $this->getPreference(self::PREF_AUTHORIZED_USERS, '')));
        $ids = array_values(array_filter($ids, static fn (int $user_id): bool => $user_id > 0));
        sort($ids, SORT_NUMERIC);

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int,array{id:int,label:string}>
     */
    private function availableUsers(): array
    {
        $users = [];

        foreach (Registry::container()->get(UserService::class)->all() as $user) {
            $users[(int) $user->id()] = [
                'id' => (int) $user->id(),
                'label' => $user->realName() . ' (' . $user->userName() . ')',
            ];
        }

        uasort($users, static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label']));

        return $users;
    }

    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . 'lang/' . $language . '.mo';

        if (!file_exists($file)) {
            return [];
        }

        // webtrees 2.3 uses its own stream-based translation loader.
        if (class_exists(\Fisharebest\Webtrees\I18N\Translation::class)) {
            $stream = fopen($file, 'rb');

            if ($stream === false) {
                return [];
            }

            try {
                return \Fisharebest\Webtrees\I18N\Translation::fromMoStream($stream)->toArray();
            } finally {
                fclose($stream);
            }
        }

        // webtrees 2.2 uses the former file-based localization package.
        if (class_exists(\Fisharebest\Localization\Translation::class)) {
            return (new \Fisharebest\Localization\Translation($file))->asArray();
        }

        return [];
    }
}
