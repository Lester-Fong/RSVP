<?php

namespace Tests\Feature\Weddings;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InvitationLinkTest extends TestCase
{
    #[DataProvider('retiredModes')]
    public function test_legacy_group_routes_remain_denied_under_every_configuration(mixed $mode): void
    {
        config(['rsvp.mode' => $mode, 'app.debug' => true]);
        $token = str_repeat('e', 64);
        $this->get('/invite/'.$token)->assertNotFound()->assertDontSee($token)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->postJson('/invite/'.$token.'/rsvp', ['version' => 0, 'responses' => []])->assertNotFound();
        foreach (['generate', 'copy', 'revoke', 'regenerate'] as $action) {
            $this->postJson('/accounts/1/weddings/1/invitation-groups/1/invitation-link/'.$action, ['link_version' => 0])->assertNotFound();
        }
    }

    public static function retiredModes(): array
    {
        return [['personal'], ['legacy'], ['invalid'], [null]];
    }

    public function test_configured_application_logs_redact_urls_tokens_nested_context_and_exception_details(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rsvp-link-log-');
        config(['logging.channels.single.path' => $path]);
        Log::forgetChannel('single');
        $token = str_repeat('e', 64);
        $url = 'https://example.test/invite/'.$token;
        try {
            Log::channel('single')->error('Request '.$url, ['token' => 'prefix'.$token.'suffix', 'nested' => ['url' => $url], 'exception' => new RuntimeException('Failure '.$url)]);
            $output = file_get_contents($path);
            $this->assertStringNotContainsString($token, $output);
            $this->assertStringContainsString('[redacted]', $output);
            $this->assertStringContainsString('RuntimeException', $output);
        } finally {
            Log::channel('single')->getLogger()->close();
            Log::forgetChannel('single');
            unlink($path);
        }
    }
}
