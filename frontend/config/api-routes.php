<?php
return [
    'GET'=>['~^/(auth/me|health|dashboard|preferences|reports/(summary|transactions|export))$~','~^/(accounts|cards|categories|merchants|transactions|transfers|installments|fixed-expenses|subscriptions|income-schedules|loans|goals|budgets|imports|card-invoices)(/\d+)?$~','~^/(loans/\d+/(payments|balances)|goals/\d+/contributions|imports/\d+/rows|card-invoices/\d+/payments|budgets/(comparison|fortnight))$~'],
    'POST'=>['~^/(accounts|cards|categories|merchants|transactions|transfers|installments|fixed-expenses|subscriptions|income-schedules|loans|goals|budgets|imports|card-invoices|forecasts/generate|planning/initialize|budgets/copy)$~','~^/(loans/\d+/(activate|payments|balances)|goals/\d+/contributions|imports/\d+/confirm|card-invoices/\d+/(payments|link-transactions))$~'],
    'PUT'=>['~^/preferences$~','~^/(accounts|cards|categories|merchants|transactions|transfers|fixed-expenses|subscriptions|income-schedules|loans|goals|budgets|import-rows)/\d+$~','~^/imports/\d+/(mapping|rows)$~'],
    'DELETE'=>['~^/(accounts|cards|categories|merchants|transactions|transfers|installments|fixed-expenses|subscriptions|income-schedules|loans|goals|goal-contributions|loan-payments|card-invoice-payments)/\d+$~'],
];
