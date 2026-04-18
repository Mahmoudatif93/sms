<?php

namespace App\WhatsappMessages\Parameters;

use InvalidArgumentException;

class DateTimeParameter extends Parameter
{


    protected ?string $fallback_value;
    protected ?string $day_of_week;
    protected ?int $year;
    protected ?int $month;
    protected ?int $day_of_month;
    protected ?int $hour;
    protected ?int $minute;
    protected ?string $calendar;

    public function __construct(?string $fallback_value = null, ?string $day_of_week = null, ?int $year = null, ?int $month = null, ?int $day_of_month = null, ?int $hour = null, ?int $minute = null, ?string $calendar = null)
    {
        parent::__construct('date_time');
        $this->fallback_value = $fallback_value;
        $this->day_of_week = $day_of_week;
        $this->year = $year;
        $this->month = $month;
        $this->day_of_month = $day_of_month;
        $this->hour = $hour;
        $this->minute = $minute;
        $this->calendar = $calendar;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'date_time' => array_filter([
                'fallback_value' => $this->fallback_value,
                'day_of_week' => $this->day_of_week,
                'year' => $this->year,
                'month' => $this->month,
                'day_of_month' => $this->day_of_month,
                'hour' => $this->hour,
                'minute' => $this->minute,
                'calendar' => $this->calendar
            ])
        ];
    }

    public function validate(): void
    {
        if (empty($this->fallback_value)) {
            throw new InvalidArgumentException("Missing fallback_value in date_time parameter");
        }
    }

}
