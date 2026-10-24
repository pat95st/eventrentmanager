<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Mail\Command\ResetEmailTemplate;
use App\Application\Mail\Command\SaveEmailTemplate;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetCompanySettings;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\EmailTemplate;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\EmailTemplateRepositoryInterface;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class EmailTemplateSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly EmailTemplateRepositoryInterface $templates,
    ) {
    }

    public function index(Request $request): void
    {
        echo View::page('settings/email-templates/index', [
            'templates' => $this->templates->getAll(),
        ], activeNav: 'settings-email-vorlagen', title: t('settings.email_templates.title'));
    }

    public function edit(Request $request): void
    {
        $key = EmailTemplateKey::tryFrom($request->param('key'));
        if ($key === null) {
            Response::notFound(t('settings.email_templates.error.unknown_key'));

            return;
        }

        $template = $this->templates->get($key);

        echo View::page('settings/email-templates/edit', [
            'key' => $key,
            'template' => $template,
            'errors' => [],
            'logoState' => $this->logoState(),
        ], activeNav: 'settings-email-vorlagen', title: t('settings.email_templates.title'));
    }

    public function update(Request $request): void
    {
        $key = EmailTemplateKey::tryFrom($request->param('key'));
        if ($key === null) {
            Response::notFound(t('settings.email_templates.error.unknown_key'));

            return;
        }

        $command = new SaveEmailTemplate(
            key: $key,
            subject: $request->input('subject'),
            bodyHtml: $request->input('body_html'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.email_templates.flash.saved'));
            Response::redirect('/einstellungen/email-vorlagen/' . $key->value);
        } catch (ValidationException $e) {
            // Zeigt die soeben abgesendeten (fehlerhaften) Werte erneut an,
            // statt der zuletzt gespeicherten Vorlage — sonst gingen die
            // Eingaben des Benutzers beim Validierungsfehler verloren.
            $template = new EmailTemplate($key, (string) $command->subject, (string) $command->bodyHtml);

            echo View::page('settings/email-templates/edit', [
                'key' => $key,
                'template' => $template,
                'errors' => $e->errors(),
                'logoState' => $this->logoState(),
            ], activeNav: 'settings-email-vorlagen', title: t('settings.email_templates.title'));
        }
    }

    public function reset(Request $request): void
    {
        $key = EmailTemplateKey::tryFrom($request->param('key'));
        if ($key === null) {
            Response::notFound(t('settings.email_templates.error.unknown_key'));

            return;
        }

        $this->commands->dispatch(new ResetEmailTemplate($key));
        Flash::success(t('settings.email_templates.flash.reset'));
        Response::redirect('/einstellungen/email-vorlagen/' . $key->value);
    }

    /**
     * Ob/wie das Firmenlogo aktuell in die "Firmenlogo einfügen"-Schaltfläche
     * eingebunden werden kann (siehe edit.php sowie
     * EmailTemplateRenderer::buildLogoAttachment(), das dieselbe
     * SVG-Einschränkung zur Versandzeit erneut — server-seitig verbindlich —
     * prüft; diese Methode liefert nur die Grundlage für den Hinweistext in
     * der Oberfläche). Eine reine Dateiendungsprüfung genügt hier, ein
     * tatsächlicher Lesezugriff ist für den Oberflächenzustand nicht nötig.
     *
     * @return 'none'|'svg'|'available'
     */
    private function logoState(): string
    {
        $logoFilename = $this->queries->ask(new GetCompanySettings())->logoFilename;

        if ($logoFilename === null) {
            return 'none';
        }

        return strtolower(pathinfo($logoFilename, PATHINFO_EXTENSION)) === 'svg' ? 'svg' : 'available';
    }
}
