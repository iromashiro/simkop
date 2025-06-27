<?php
// app/Domain/Analytics/DTOs/WidgetDataDTO.php
namespace App\Domain\Analytics\DTOs;

use Carbon\Carbon;

/**
 * Widget Data Transfer Object
 * SRS Reference: Section 3.6.3 - Widget Data Structure
 */
class WidgetDataDTO
{
    public function __construct(
        public string $type,
        public string $title,
        public array $data,
        public array $metadata = [],
        public array $chartConfig = [],
        public ?string $description = null,
        public ?string $icon = null,
        public array $actions = [],
        public ?string $lastUpdated = null
    ) {
        $this->lastUpdated = $this->lastUpdated ?? Carbon::now()->toISOString();
        $this->validateData();
    }

    /**
     * Create financial widget
     */
    public static function financial(
        string $title,
        array $data,
        array $chartConfig = [],
        ?string $description = null
    ): self {
        return new self(
            type: 'financial',
            title: $title,
            data: $data,
            chartConfig: $chartConfig,
            description: $description,
            icon: 'fas fa-chart-line'
        );
    }

    /**
     * Create member widget
     */
    public static function member(
        string $title,
        array $data,
        array $chartConfig = [],
        ?string $description = null
    ): self {
        return new self(
            type: 'member',
            title: $title,
            data: $data,
            chartConfig: $chartConfig,
            description: $description,
            icon: 'fas fa-users'
        );
    }

    /**
     * Create savings widget
     */
    public static function savings(
        string $title,
        array $data,
        array $chartConfig = [],
        ?string $description = null
    ): self {
        return new self(
            type: 'savings',
            title: $title,
            data: $data,
            chartConfig: $chartConfig,
            description: $description,
            icon: 'fas fa-piggy-bank'
        );
    }

    /**
     * Create loan widget
     */
    public static function loan(
        string $title,
        array $data,
        array $chartConfig = [],
        ?string $description = null
    ): self {
        return new self(
            type: 'loan',
            title: $title,
            data: $data,
            chartConfig: $chartConfig,
            description: $description,
            icon: 'fas fa-hand-holding-usd'
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'chart_config' => $this->chartConfig,
            'description' => $this->description,
            'icon' => $this->icon,
            'actions' => $this->actions,
            'last_updated' => $this->lastUpdated,
        ];
    }

    /**
     * Add metadata
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Add action
     */
    public function addAction(string $label, string $url, string $method = 'GET'): self
    {
        $this->actions[] = [
            'label' => $label,
            'url' => $url,
            'method' => $method,
        ];
        return $this;
    }

    /**
     * Set chart configuration
     */
    public function setChartConfig(array $config): self
    {
        $this->chartConfig = $config;
        return $this;
    }

    /**
     * Validate data structure
     */
    private function validateData(): void
    {
        if (empty($this->type)) {
            throw new \InvalidArgumentException('Widget type cannot be empty');
        }

        if (empty($this->title)) {
            throw new \InvalidArgumentException('Widget title cannot be empty');
        }

        if (!is_array($this->data)) {
            throw new \InvalidArgumentException('Widget data must be an array');
        }
    }
}
