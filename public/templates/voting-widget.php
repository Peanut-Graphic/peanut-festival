<?php
if (!defined('ABSPATH')) exit;

$show_slug = esc_attr($atts['show_slug']);
// The server accepts at most Peanut_Festival_Voting::MAX_BALLOT_RANKS choices.
$top_n = max(1, min(Peanut_Festival_Voting::MAX_BALLOT_RANKS, intval($atts['top_n'])));
$show_timer = intval($atts['show_timer']);
?>

<div class="pf-voting-widget"
     data-show="<?php echo $show_slug; ?>"
     data-top-n="<?php echo $top_n; ?>"
     data-show-timer="<?php echo $show_timer; ?>">

    <div class="pf-vote-loading" role="status" aria-live="polite">
        <div class="pf-spinner" aria-hidden="true"></div>
        <p>Loading voting interface...</p>
    </div>

    <div class="pf-vote-content" style="display:none;">
        <div class="pf-vote-header">
            <h2 class="pf-vote-title">Cast Your Vote</h2>
            <div class="pf-vote-group-name"></div>
            <div class="pf-vote-timer" style="display:none;">
                <span class="pf-timer-label">Time remaining:</span>
                <span class="pf-timer-display">--:--</span>
            </div>
        </div>

        <div class="pf-vote-instructions">
            <p>Select your top <strong><?php echo $top_n; ?></strong> performer(s) in order of preference, then submit your vote.</p>
            <p class="pf-vote-hint">Click performers in the order you want to rank them (1st, 2nd, 3rd...)</p>
        </div>

        <div class="pf-vote-message" role="alert" aria-live="polite"></div>

        <div class="pf-performers-grid" role="listbox" aria-label="Performers to vote for" aria-multiselectable="true"></div>

        <div class="pf-vote-selections">
            <h4>Your Selections:</h4>
            <div class="pf-selections-list"></div>
        </div>

        <div class="pf-vote-actions">
            <button type="button" class="pf-clear-vote" aria-label="Clear all selections">Clear Selection</button>
            <button type="button" class="pf-submit-vote" disabled aria-disabled="true">Submit Vote</button>
        </div>
    </div>

    <div class="pf-vote-closed" style="display:none;" role="alert" aria-live="polite">
        <div class="pf-notice">
            <h3>Voting Closed</h3>
            <p>Voting is not currently open. Please wait for the host to start the next round.</p>
        </div>
    </div>

    <div class="pf-vote-success" style="display:none;" role="alert" aria-live="polite">
        <div class="pf-success">
            <h3>Thank You!</h3>
            <p>Your vote has been recorded successfully.</p>
        </div>
    </div>

    <div class="pf-vote-already" style="display:none;" role="alert" aria-live="polite">
        <div class="pf-notice">
            <h3>Already Voted</h3>
            <p>You have already voted in this round. Please wait for the next round.</p>
        </div>
    </div>
</div>
