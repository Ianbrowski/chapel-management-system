<?php

class InsightPortfolio
{
    public function build(array $intelligence, array $donationStats = []): array
    {
        $cards = [
            'pastoral' => $this->defaultCard('pastoral', 'Pastoral Balance', 'Efficiency', 'fa-solid fa-people-group', 'pulse-pastor'),
            'growth' => $this->defaultCard('growth', 'Growth Pulse', 'Demographics', 'fa-solid fa-chart-line', 'pulse-growth'),
            'finance' => $this->defaultCard('finance', 'Financial Forecast', 'Finance', 'fa-solid fa-money-bill-trend-up', 'pulse-finance'),
            'retention' => $this->defaultCard('retention', 'Retention Pulse', 'Engagement', 'fa-solid fa-heart-pulse', 'pulse-retention'),
            'facility' => $this->defaultCard('facility', 'Facility Health', 'Operations', 'fa-solid fa-tools', 'pulse-facility'),
            'efficiency' => $this->defaultCard('efficiency', 'Efficiency Pulse', 'Service', 'fa-solid fa-bolt', 'pulse-efficiency'),
            'engagement' => $this->defaultCard('engagement', 'User Engagement', 'Community', 'fa-solid fa-users-viewfinder', 'pulse-users'),
        ];

        $cards['pastoral'] = $this->buildPastoralCard($cards['pastoral'], (array) ($intelligence['pastor_load'] ?? []));
        $cards['growth'] = $this->buildGrowthCard($cards['growth'], (array) ($intelligence['growth_ratio'] ?? []));
        $cards['finance'] = $this->buildFinanceCard($cards['finance'], (array) $donationStats);
        $cards['retention'] = $this->buildRetentionCard($cards['retention'], (array) ($intelligence['retention_stats'] ?? []));
        $cards['facility'] = $this->buildFacilityCard($cards['facility'], (array) ($intelligence['usage_stats'] ?? []));
        $cards['efficiency'] = $this->buildEfficiencyCard($cards['efficiency'], $intelligence['response_time'] ?? null);
        $cards['engagement'] = $this->buildEngagementCard($cards['engagement'], (array) ($intelligence['member_stats'] ?? []), (array) ($intelligence['account_growth'] ?? []), (array) ($intelligence['member_detailed'] ?? []), $intelligence['scope_name'] ?? 'System-Wide');

        return [
            'cards' => array_values($cards),
            'recommendations' => $this->buildRecommendations($cards),
            'venue_demand' => array_values((array) ($intelligence['venue_demand'] ?? [])),
            'default_card' => 'pastoral',
        ];
    }

    private function defaultCard(string $key, string $title, string $tag, string $icon, string $iconClass): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'tag' => $tag,
            'icon' => $icon,
            'icon_class' => $iconClass,
            'status' => 'Monitoring',
            'summary' => 'Detailed explanation will appear here.',
            'narrative' => 'Monitoring is active but there is not enough data yet to surface a strong recommendation.',
            'metrics' => ['Not enough data is available yet.'],
            'actions' => ['Wait for more records before acting on this insight.'],
            'card_message' => 'Not enough data to evaluate this insight.',
            'score' => 'Low data',
        ];
    }

    private function buildPastoralCard(array $card, array $pastorLoad): array
    {
        $card['summary'] = 'Clergy load is being compared across active assignments.';
        $card['narrative'] = 'The portfolio evaluates whether event assignments are concentrated on one pastor or spread in a sustainable way. Large imbalances usually indicate staffing pressure and scheduling fragility.';
        $card['metrics'] = ['No pastor load records are available yet.'];
        $card['actions'] = ['Wait for more confirmed event assignments to measure pastoral load accurately.'];
        $card['card_message'] = 'Not enough assignment data to evaluate pastoral balance.';

        if (empty($pastorLoad)) {
            return $card;
        }

        usort($pastorLoad, fn($a, $b) => (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0));
        $top = $pastorLoad[0];
        $bottom = $pastorLoad[count($pastorLoad) - 1];
        $gap = max(0, (int) ($top['count'] ?? 0) - (int) ($bottom['count'] ?? 0));
        $balanced = $gap <= 2;

        $card['status'] = $balanced ? 'Stable' : ($gap > 5 ? 'Attention' : 'Watch');
        $card['summary'] = $balanced
            ? 'Assignments are relatively balanced across the pastoral roster.'
            : sprintf('%s is carrying the heaviest load with a %s-event gap over the lightest assignment.', $top['pastor'] ?? 'Top pastor', $this->formatInt($gap));
        $card['narrative'] = $balanced
            ? 'Event ownership is distributed closely enough that burnout risk appears controlled. Keep watching for sudden schedule spikes before peak seasons.'
            : sprintf('Pastoral work is currently concentrated. %s is taking a disproportionate share of the event calendar, which increases continuity risk if new requests keep flowing to the same person.', $top['pastor'] ?? 'One pastor');
        $card['metrics'] = [
            sprintf('%s: %s assigned events', $top['pastor'] ?? 'Highest load', $this->formatInt($top['count'] ?? 0)),
            sprintf('%s: %s assigned events', $bottom['pastor'] ?? 'Lowest load', $this->formatInt($bottom['count'] ?? 0)),
            sprintf('Load spread across roster: %s event difference', $this->formatInt($gap)),
        ];
        $card['actions'] = $balanced
            ? ['Keep current assignment rules, but review again before high-demand liturgical periods.']
            : [
                sprintf('Rebalance upcoming assignments away from %s.', $top['pastor'] ?? 'the most-loaded pastor'),
                'Review whether preference routing or manual approval is concentrating load.',
                'Prepare backup assignment coverage for overload weeks.',
            ];
        $card['card_message'] = $balanced
            ? 'Clergy workload is balanced across the current schedule.'
            : sprintf('Imbalance: %s has %s more events than the minimum load.', $top['pastor'] ?? 'A pastor', $this->formatInt($gap));
        $card['score'] = $card['status'];

        return $card;
    }

    private function buildGrowthCard(array $card, array $growthRatio): array
    {
        $card['summary'] = 'The system is comparing life-event volume to infer growth direction.';
        $card['narrative'] = 'This view compares baptism and funeral activity as a rough demographic pulse. A higher baptism-to-funeral ratio often suggests stronger family formation and future ministry demand.';
        $card['metrics'] = ['No sacramental trend data is available yet.'];
        $card['actions'] = ['Collect more sacramental records to improve demographic confidence.'];
        $card['card_message'] = 'Not enough data to evaluate growth pulse.';

        $baptisms = (int) ($growthRatio['Baptism'] ?? 0);
        $funerals = (int) ($growthRatio['Funeral'] ?? 0);
        if ($baptisms <= 0 && $funerals <= 0) {
            return $card;
        }

        $ratio = $baptisms / max($funerals, 1);
        $ratioLabel = number_format($ratio, 1);
        $strongGrowth = $ratio >= 2;

        $card['status'] = $strongGrowth ? 'Expansion' : ($ratio >= 1.2 ? 'Stable' : 'Caution');
        $card['summary'] = sprintf(
            'Baptism-to-funeral ratio is %sx, signaling %s.',
            $ratioLabel,
            $strongGrowth ? 'expansion pressure' : ($ratio >= 1.2 ? 'steady population momentum' : 'a soft growth profile')
        );
        $card['narrative'] = $strongGrowth
            ? 'Recent sacramental patterns suggest stronger family intake than end-of-life volume. This usually means children, youth, and family ministry demand will rise first.'
            : ($ratio >= 1.2
                ? 'The parish appears stable with moderate growth signals. There is enough activity to justify continuity planning without overextending resources.'
                : 'Growth indicators are soft. That does not mean decline by itself, but it does suggest the parish should confirm whether outreach and family engagement are keeping pace.');
        $card['metrics'] = [
            sprintf('Baptism records: %s', $this->formatInt($baptisms)),
            sprintf('Funeral records: %s', $this->formatInt($funerals)),
            sprintf('Growth ratio: %sx', $ratioLabel),
        ];
        $card['actions'] = $strongGrowth
            ? ['Scale youth and family formation capacity before demand catches operations off guard.']
            : ($ratio >= 1.2
                ? ['Maintain current ministry capacity and review demographic momentum monthly.']
                : ['Validate local outreach strategy and review parish family engagement activity.']);
        $card['card_message'] = sprintf(
            'Population is growing at %sx. %s',
            $ratioLabel,
            $strongGrowth ? 'Strategic expansion recommended.' : ($ratio >= 1.2 ? 'Status is stable.' : 'Growth is soft.')
        );
        $card['score'] = $card['status'];

        return $card;
    }

    private function buildFinanceCard(array $card, array $donationStats): array
    {
        $card['summary'] = 'Projected revenue is estimated from recorded donation momentum.';
        $card['narrative'] = 'This forecast is directional, not audited. It uses current donation totals as a baseline and highlights whether the church can plan conservatively or has room for more proactive programming.';
        $card['metrics'] = ['No donation totals are available yet.'];
        $card['actions'] = ['Record more donation activity to generate a stronger forecast.'];
        $card['card_message'] = 'Donation records are still too limited for a useful forecast.';

        $total = (float) ($donationStats['total_amount'] ?? 0);
        if ($total <= 0) {
            return $card;
        }

        $forecast = $total * 1.05;
        $strongBase = $total >= 1000000;

        $card['status'] = $strongBase ? 'Strong base' : 'Steady';
        $card['summary'] = sprintf('Projected next-month donation trend is %s based on current giving volume.', $this->formatCurrency($forecast));
        $card['narrative'] = $strongBase
            ? 'Giving momentum is already strong enough to support forward planning. Leadership can evaluate whether to reserve, invest in ministry, or fund overdue operational work.'
            : 'Donation volume shows a workable baseline, but planning should stay conservative. Forecast confidence improves as recurring giving becomes more predictable.';
        $card['metrics'] = [
            sprintf('Current donation total: %s', $this->formatCurrency($total)),
            sprintf('Projected next month: %s', $this->formatCurrency($forecast)),
            'Forecast assumption: 5% continuation uplift',
        ];
        $card['actions'] = $strongBase
            ? ['Review where surplus can fund parish improvements without eroding reserves.']
            : ['Protect essential spending and keep donation follow-up campaigns consistent.'];
        $card['card_message'] = sprintf('Next month estimate: %s. Revenue trend is steady.', $this->formatCurrency($forecast));
        $card['score'] = $card['status'];

        return $card;
    }

    private function buildRetentionCard(array $card, array $retention): array
    {
        $card['summary'] = 'Retention compares sacramental follow-through from Baptism to Confirmation.';
        $card['narrative'] = 'A weak baptism-to-confirmation pipeline can indicate disengagement in catechesis, family follow-up, or youth ministry continuity. This card highlights potential drop-off pressure.';
        $card['metrics'] = ['No retention chain data is available yet.'];
        $card['actions'] = ['Track both Baptism and Confirmation consistently to see retention trends.'];
        $card['card_message'] = 'Not enough data to evaluate retention pulse.';

        $baptisms = (int) ($retention['Baptism'] ?? 0);
        $confirmations = (int) ($retention['Confirmation'] ?? 0);
        if ($baptisms <= 0) {
            return $card;
        }

        $rate = ($confirmations / $baptisms) * 100;
        $rateLabel = number_format($rate, 1);

        $card['status'] = $rate < 50 ? 'Risk' : ($rate < 75 ? 'Watch' : 'Healthy');
        $card['summary'] = sprintf('Baptism-to-Confirmation follow-through is %s%%, showing how many families remain connected deeper into formation.', $rateLabel);
        $card['narrative'] = $rate < 50
            ? 'The sacramental pipeline is losing too many people before Confirmation. This usually points to weak family follow-up, catechetical disengagement, or poor continuity in youth ministry.'
            : ($rate < 75
                ? 'Retention is workable but uneven. There is room to tighten follow-up after Baptism and strengthen later sacramental engagement.'
                : 'The parish is retaining a strong share of sacramental participants into Confirmation. That suggests healthy long-term engagement.');
        $card['metrics'] = [
            sprintf('Baptism records: %s', $this->formatInt($baptisms)),
            sprintf('Confirmation records: %s', $this->formatInt($confirmations)),
            sprintf('Retention rate: %s%%', $rateLabel),
        ];
        $card['actions'] = $rate < 50
            ? [
                'Review First Communion and Confirmation engagement strategy.',
                'Strengthen family follow-up after Baptism enrollment.',
                'Identify the age segment with the largest drop-off.',
            ]
            : ($rate < 75
                ? ['Audit catechetical continuity and improve reminders for sacramental progression.']
                : ['Keep the current engagement model and document what is working.']);
        $card['card_message'] = sprintf('Baptism-to-Confirmation rate is %s%%. Monitoring drop-off points.', $rateLabel);
        $card['score'] = $card['status'];

        return $card;
    }

    private function buildFacilityCard(array $card, array $usage): array
    {
        $card['summary'] = 'Facility load is estimated from venue usage concentration.';
        $card['narrative'] = 'The engine watches which venue is carrying the highest share of church activity. Heavy concentration can translate into wear, staffing strain, and maintenance risk.';
        $card['metrics'] = ['No venue usage data is available yet.'];
        $card['actions'] = ['Wait for more event scheduling data to profile facility load.'];
        $card['card_message'] = 'Not enough venue usage data to evaluate facility health.';

        if (empty($usage)) {
            return $card;
        }

        usort($usage, fn($a, $b) => (int) ($b['total_events'] ?? 0) <=> (int) ($a['total_events'] ?? 0));
        $top = $usage[0];
        $load = (int) ($top['total_events'] ?? 0);

        $card['status'] = $load > 50 ? 'Heavy load' : ($load > 20 ? 'Busy' : 'Normal');
        $card['summary'] = sprintf('%s currently carries the highest venue load at %s scheduled events.', $top['chapel_location'] ?? 'Top venue', $this->formatInt($load));
        $card['narrative'] = $load > 50
            ? 'Usage is dense enough to justify preventive maintenance and calendar smoothing. High concentration increases the chance of wear, staffing conflict, and downtime pressure.'
            : ($load > 20
                ? 'The venue is active, but still manageable. Maintenance planning should stay ahead of the schedule before volume spikes.'
                : 'Current facility demand is moderate. The venue can absorb more activity without obvious strain.');
        $card['metrics'] = [
            sprintf('Top-demand venue: %s', $top['chapel_location'] ?? 'Unknown'),
            sprintf('Total scheduled events: %s', $this->formatInt($load)),
            sprintf('Operational state: %s', $card['status']),
        ];
        $card['actions'] = $load > 50
            ? [
                sprintf('Schedule preventive structural audit for %s.', $top['chapel_location'] ?? 'the top-demand venue'),
                'Distribute suitable events across less-used venues where possible.',
            ]
            : ['Keep preventive maintenance aligned with the current usage tempo.'];
        $card['card_message'] = sprintf('%s is at %s events. Usage is high.', $top['chapel_location'] ?? 'Top venue', $this->formatInt($load));
        $card['score'] = $card['status'];

        return $card;
    }

    private function buildEfficiencyCard(array $card, $responseTime): array
    {
        $card['summary'] = 'Administrative responsiveness is evaluated from average handling time.';
        $card['narrative'] = 'This card estimates how quickly the parish responds to workflow demand. Long response time usually points to staffing gaps, overloaded queues, or approval bottlenecks.';
        $card['metrics'] = ['No response-time metric is available yet.'];
        $card['actions'] = ['Capture more workflow completion timestamps to calculate service speed.'];
        $card['card_message'] = 'Not enough data to calculate efficiency pulse.';

        if ($responseTime === null || $responseTime === '') {
            return $card;
        }

        $hours = (float) $responseTime;
        $days = $hours / 24;
        $dayLabel = number_format($days, 1);

        $card['status'] = $hours == 0.0 ? 'Instant' : ($days > 2 ? 'Delayed' : ($days > 1 ? 'Watch' : 'Healthy'));
        $card['summary'] = $hours == 0.0
            ? 'Administrative handling is effectively instant based on current workflow data.'
            : sprintf('Average response time is %s days across measured requests.', $dayLabel);
        $card['narrative'] = $hours == 0.0
            ? 'The recorded workflow suggests exceptionally fast turnaround. Confirm this reflects real throughput and not incomplete status tracking.'
            : ($days > 2
                ? 'Service speed is slipping into visible delay. Users are likely feeling friction, and approval queues or staffing levels should be reviewed.'
                : 'Response time is within a workable range, though the team should keep watching for queue buildup.');
        $card['metrics'] = [
            sprintf('Average response time: %s', $hours == 0.0 ? 'Instant' : "{$dayLabel} days"),
            sprintf('Source metric: %s hours', number_format($hours, 1)),
            sprintf('Service state: %s', $card['status']),
        ];
        $card['actions'] = $days > 2
            ? [
                sprintf('Response bottleneck detected at %s days.', $dayLabel),
                'Review whether more Chapel Admin coverage is needed.',
                'Inspect approval stages causing the longest wait.',
            ]
            : ($hours == 0.0
                ? ['Validate that workflow timestamps are being captured correctly to preserve confidence in the metric.']
                : ['Maintain current staffing and review turnaround weekly.']);
        $card['card_message'] = $hours == 0.0
            ? 'Average response time: Instant. Maximum efficiency reached.'
            : sprintf('Average response time: %s days. %s', $dayLabel, $days > 2 ? 'High visibility needed.' : 'Good performance.');
        $card['score'] = $card['status'];

        return $card;
    }

    private function buildRecommendations(array $cards): array
    {
        $recommendations = [];
        foreach ($cards as $card) {
            foreach ((array) ($card['actions'] ?? []) as $action) {
                $recommendations[$action] = [
                    'text' => $action,
                    'key' => $card['key'],
                ];
            }
        }

        return array_values(array_slice($recommendations, 0, 6));
    }

    private function buildEngagementCard(array $card, array $memberStats, array $growth, array $detailed, string $scopeName = 'System-Wide'): array
    {
        $card['tag'] = $scopeName;
        $card['summary'] = sprintf('User role distribution for %s is being monitored.', $scopeName);
        $card['narrative'] = 'This view tracks the balance between administrative users and the broader parish community. A healthy system should show a high ratio of Members to Admins/Staff.';
        $card['metrics'] = ['No account distribution data is available yet.'];
        $card['actions'] = ['Encourage more parishioners to register for the Member portal.'];
        $card['card_message'] = 'Not enough user data to evaluate community engagement.';

        $total = (int) ($memberStats['total_users'] ?? 0);
        $members = (int) ($memberStats['member_count'] ?? 0);
        
        if ($members === 0 && $total > 0) {
            $card['status'] = 'Setup';
            $card['summary'] = sprintf('Current accounts for %s consist only of Staff and Administrators.', $scopeName);
            $card['narrative'] = 'You have registered the management team, but no Parish Members have been onboarded to this chapel yet. This is why most engagement percentages show 0%.';
            $card['actions'] = [
                'Onboard your first members to see engagement analytics.',
                'Ensure members are assigned to ' . $scopeName . ' in their profile.',
                'Check if users were registered with the "Member" role.'
            ];
        }

        if ($total <= 0) {
            return $card;
        }

        $memberRate = ($members / $total) * 100;
        if ($members > 0) {
            $card['status'] = $memberRate > 60 ? 'Thriving' : ($memberRate > 30 ? 'Growing' : 'Admin Heavy');
            $card['summary'] = sprintf('The %s community has %s registered users, with %s%% identifying as Parish Members.', $scopeName, $this->formatInt($total), number_format($memberRate, 1));
            $card['narrative'] = $memberRate > 60
                ? 'The system has strong community adoption. Most accounts belong to parishioners rather than staff, suggesting the digital portal is becoming a primary engagement tool.'
                : ($memberRate > 30
                    ? 'Member enrollment is growing. The balance between administrative users and the community is stabilizing, but outreach can still improve adoption.'
                    : 'Account distribution is currently weighted toward administrative roles. Focus on onboarding more parishioners to shift the portal from a management-only tool.');
        }
        $hubs = (array) ($detailed['activity_hubs'] ?? []);
        $card['metrics'] = [
            '[HEADER:Branch Capacity]',
            sprintf('Total Branch Accounts: %s', $this->formatInt($total)),
            sprintf('Management Admins: %s', $this->formatInt($memberStats['admin_count'] ?? 0)),
            sprintf('Operations Staff: %s', $this->formatInt($memberStats['staff_count'] ?? 0)),
            sprintf('Assigned Priests: %s', $this->formatInt($memberStats['priest_count'] ?? $memberStats['pastor_count'] ?? 0)),
            '---',
            '[HEADER:Community Performance]',
            sprintf('Active Chapel Members: %s', $this->formatInt($members)),
            sprintf('Unassigned Members: %s', $this->formatInt($memberStats['unassigned_count'] ?? 0)),
            sprintf('Engaged Accounts: %s%% log-in rate', $members > 0 ? number_format((($detailed['engaged_users'] ?? 0) / $members) * 100, 1) : 0),
            sprintf('Service Adoption: %s%% request rate', $members > 0 ? number_format((($detailed['active_requesters'] ?? 0) / $members) * 100, 1) : 0),
            sprintf('Verified Reach: %s%% email verified', $members > 0 ? number_format((($detailed['has_email'] ?? 0) / $members) * 100, 1) : 0),
            '---',
            '[HEADER:Recent Activity Hubs]'
        ];
        foreach ($hubs as $hub) {
            $card['metrics'][] = sprintf('%s: %s members', $hub['hub_name'] ?? 'Main Cathedral', $this->formatInt($hub['member_count'] ?? 0));
        }

        if (empty($hubs)) {
            $card['metrics'][] = 'No active hubs detected yet.';
        }

        $card['metrics'][] = '---';
        $card['metrics'][] = sprintf('Adoption Momentum: %s%% community-led', number_format($memberRate, 1));
        $card['metrics'][] = sprintf('Overall Health: %s%% profile completion', $members > 0 ? number_format((($detailed['has_pic'] ?? 0) / $members) * 100, 1) : 0);
        
        $card['actions'] = $memberRate > 60
            ? ['Continue promoting digital donation and event request features to the active member base.']
            : [
                'Launch a registration drive during weekend masses or chapel meetings.',
                'Distribute QR codes for easy account creation.',
                'Review whether the Member role onboarding process is intuitive.'
            ];
            
        $card['card_message'] = sprintf('Member Adoption: %s%%. Community footprint is %s.', number_format($memberRate, 1), $card['status']);
        $card['score'] = $card['status'];

        return $card;
    }

    private function formatInt($value): string
    {
        return number_format((int) $value);
    }

    private function formatCurrency($value): string
    {
        return 'PHP ' . number_format((float) $value, 0);
    }
}
