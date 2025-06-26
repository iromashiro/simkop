<?php
// app/Domain/Notification/Models/NotificationTemplate.php
namespace App\Domain\Notification\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends TenantModel
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'cooperative_id',
        'name',
        'type',
        'subject',
        'body_html',
        'body_text',
        'sms_template',
        'variables',
        'channels',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'channels' => 'array',
        'is_active' => 'boolean',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'created_by');
    }

    /**
     * ✅ FIXED: Validate template content and structure
     */
    public function validateTemplate(): array
    {
        $errors = [];

        // Validate required variables
        $requiredVariables = $this->variables['required'] ?? [];
        foreach ($requiredVariables as $variable) {
            if (!str_contains($this->body_html, "{{$variable}}")) {
                $errors[] = "Required variable {{$variable}} not found in HTML template";
            }
            if (!str_contains($this->body_text, "{{$variable}}")) {
                $errors[] = "Required variable {{$variable}} not found in text template";
            }
            if ($this->sms_template && !str_contains($this->sms_template, "{{$variable}}")) {
                $errors[] = "Required variable {{$variable}} not found in SMS template";
            }
        }

        // Validate template syntax
        if ($this->body_html && !$this->isValidHtml($this->body_html)) {
            $errors[] = "Invalid HTML syntax in template";
        }

        // Validate subject template
        if (empty($this->subject)) {
            $errors[] = "Subject template is required";
        }

        // Validate channels
        $validChannels = ['email', 'sms', 'database', 'push'];
        foreach ($this->channels as $channel) {
            if (!in_array($channel, $validChannels)) {
                $errors[] = "Invalid channel: {$channel}";
            }
        }

        // Validate channel-specific requirements
        if (in_array('email', $this->channels)) {
            if (empty($this->body_html) && empty($this->body_text)) {
                $errors[] = "Email channel requires either HTML or text body";
            }
        }

        if (in_array('sms', $this->channels)) {
            if (empty($this->sms_template)) {
                $errors[] = "SMS channel requires SMS template";
            } elseif (strlen($this->sms_template) > 160) {
                $errors[] = "SMS template exceeds 160 characters limit";
            }
        }

        // Validate variable syntax
        $allContent = $this->subject . ' ' . $this->body_html . ' ' . $this->body_text . ' ' . $this->sms_template;
        $variablePattern = '/\{\{([^}]+)\}\}/';
        preg_match_all($variablePattern, $allContent, $matches);

        $usedVariables = array_unique($matches[1]);
        $definedVariables = array_merge(
            $this->variables['required'] ?? [],
            $this->variables['optional'] ?? []
        );

        foreach ($usedVariables as $variable) {
            if (!in_array($variable, $definedVariables)) {
                $errors[] = "Undefined variable used: {{$variable}}";
            }
        }

        return $errors;
    }

    /**
     * Check if HTML is valid
     */
    private function isValidHtml(string $html): bool
    {
        if (empty($html)) {
            return true;
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        $errors = libxml_get_errors();
        libxml_clear_errors();

        return empty($errors);
    }

    /**
     * ✅ FIXED: Generate template preview with sample data
     */
    public function generatePreview(array $sampleData = []): array
    {
        $defaultSampleData = [
            'member_name' => 'John Doe',
            'amount' => 'Rp 1.000.000',
            'date' => now()->format('d/m/Y'),
            'cooperative_name' => 'Koperasi Sejahtera',
            'balance' => 'Rp 5.000.000',
            'loan_amount' => 'Rp 10.000.000',
            'payment_due' => now()->addDays(30)->format('d/m/Y'),
            'interest_rate' => '12%',
            'transaction_id' => 'TXN-' . now()->format('YmdHis'),
        ];

        $data = array_merge($defaultSampleData, $sampleData);

        return [
            'subject' => $this->renderSubject($data),
            'body_html' => $this->renderBodyHtml($data),
            'body_text' => $this->renderBodyText($data),
            'sms_template' => $this->renderSmsTemplate($data),
            'validation_errors' => $this->validateTemplate(),
            'character_counts' => [
                'subject' => strlen($this->renderSubject($data)),
                'body_html' => strlen($this->renderBodyHtml($data)),
                'body_text' => strlen($this->renderBodyText($data)),
                'sms_template' => strlen($this->renderSmsTemplate($data)),
            ],
        ];
    }

    /**
     * Get template statistics
     */
    public function getTemplateStatistics(): array
    {
        return [
            'variable_count' => count($this->variables['required'] ?? []) + count($this->variables['optional'] ?? []),
            'required_variables' => count($this->variables['required'] ?? []),
            'optional_variables' => count($this->variables['optional'] ?? []),
            'channels_count' => count($this->channels),
            'estimated_sms_cost' => $this->estimateSMSCost(),
            'complexity_score' => $this->calculateComplexityScore(),
        ];
    }

    /**
     * Estimate SMS cost based on template length
     */
    private function estimateSMSCost(): float
    {
        if (!in_array('sms', $this->channels) || empty($this->sms_template)) {
            return 0;
        }

        $length = strlen($this->sms_template);
        $segments = ceil($length / 160);
        $costPerSegment = 0.05; // $0.05 per SMS segment

        return $segments * $costPerSegment;
    }

    /**
     * Calculate template complexity score
     */
    private function calculateComplexityScore(): int
    {
        $score = 0;

        // Base score for each channel
        $score += count($this->channels) * 10;

        // Score for variables
        $score += count($this->variables['required'] ?? []) * 5;
        $score += count($this->variables['optional'] ?? []) * 3;

        // Score for HTML complexity
        if ($this->body_html) {
            $htmlTags = substr_count($this->body_html, '<');
            $score += min($htmlTags * 2, 50); // Cap at 50 points
        }

        return min($score, 100); // Cap at 100 points
    }

    public function renderSubject(array $data = []): string
    {
        return $this->replaceVariables($this->subject, $data);
    }

    public function renderBodyHtml(array $data = []): string
    {
        return $this->replaceVariables($this->body_html, $data);
    }

    public function renderBodyText(array $data = []): string
    {
        return $this->replaceVariables($this->body_text, $data);
    }

    public function renderSmsTemplate(array $data = []): string
    {
        return $this->replaceVariables($this->sms_template, $data);
    }

    /**
     * ✅ ENHANCED: Improved variable replacement with validation
     */
    private function replaceVariables(string $template, array $data): string
    {
        if (empty($template)) {
            return '';
        }

        // Replace variables with proper escaping
        foreach ($data as $key => $value) {
            $placeholder = "{{$key}}";
            $template = str_replace($placeholder, (string) $value, $template);
        }

        // Check for unreplaced variables
        $variablePattern = '/\{\{([^}]+)\}\}/';
        preg_match_all($variablePattern, $template, $matches);

        if (!empty($matches[1])) {
            \Log::warning('Template has unreplaced variables', [
                'template_id' => $this->id,
                'unreplaced_variables' => $matches[1],
            ]);

            // Replace unreplaced variables with placeholder text
            foreach ($matches[1] as $variable) {
                $template = str_replace("{{$variable}}", "[{$variable}]", $template);
            }
        }

        return $template;
    }

    /**
     * Test template with validation
     */
    public function testTemplate(array $testData = []): array
    {
        $validationErrors = $this->validateTemplate();

        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'errors' => $validationErrors,
                'preview' => null,
            ];
        }

        try {
            $preview = $this->generatePreview($testData);

            return [
                'success' => true,
                'errors' => [],
                'preview' => $preview,
                'statistics' => $this->getTemplateStatistics(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Template rendering failed: ' . $e->getMessage()],
                'preview' => null,
            ];
        }
    }
}
