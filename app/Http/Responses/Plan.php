<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class Plan extends DataInterface
{
    public ?int $id;
    public int $points_cnt;
    public float $price;
    public string $currency;
    public string $method;
    public string $created_at;

    public function __construct(\App\Models\Plan $plan)
    {
        $this->id = $plan->pivot->id;
        $this->points_cnt = $plan->pivot->points_cnt;
        $this->price = $plan->pivot->price;
        $this->currency = $plan->pivot->currency;
        $this->method = $plan->method;
        $this->created_at = $plan->pivot->created_at;

    }
}
