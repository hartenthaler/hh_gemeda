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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure\GeMeDaLinkReader;

use function file_exists;

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
            'can_create_claims' => $this->canCreateClaims(),
            'api_configured' => $this->apiConfigured(),
        ]);
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'api_base_url' => $this->getPreference(self::PREF_API_BASE_URL, ''),
            'service_key_configured' => $this->getPreference(self::PREF_SERVICE_KEY, '') !== '',
            'contributor_pepper_configured' => $this->getPreference(self::PREF_CONTRIBUTOR_PEPPER, '') !== '',
            'users' => $this->availableUsers(),
            'authorized_user_ids' => $this->authorizedUserIds(),
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $this->setPreference(self::PREF_API_BASE_URL, trim((string) ($body['api_base_url'] ?? '')));

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

    private function apiConfigured(): bool
    {
        return trim((string) $this->getPreference(self::PREF_API_BASE_URL, '')) !== ''
            && trim((string) $this->getPreference(self::PREF_SERVICE_KEY, '')) !== '';
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
