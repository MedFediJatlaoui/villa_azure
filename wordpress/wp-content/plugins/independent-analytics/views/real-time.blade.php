<div data-controller="real-time"
     data-real-time-chart-data-value="<?php echo esc_attr(json_encode($chart_data)) ?>"
     data-real-time-starting-at-value="<?php echo esc_attr($starting_at) ?>"
     data-real-time-nonce-value="<?php echo wp_create_nonce('iawp_real_time') ?>"
     data-chart-locale-value="<?php echo esc_attr(get_bloginfo('language')); ?>"
>
    <div id="report-title-bar" class="report-title-bar">
        <div class="primary-report-title-container">
            <h1 class="report-title"><?php esc_html_e('Real-Time', 'independent-analytics'); ?></h1>
        </div>

        <div class="buttons">
            <button data-action="real-time#toggleLiveRefreshing"
                    class="iawp-button real-time-pause"
                    data-real-time-target="pauseButton"
                    data-pause-title="<?php echo esc_attr_e('Pause updates') ?>"
                    data-play-title="<?php echo esc_attr_e('Resume updates') ?>"
            >
                <span class="real-time-progress-bar"></span>
                <span class="dashicons dashicons-controls-pause"></span>
                <span class="dashicons dashicons-controls-play"></span>
            </button>
            <button id="favorite-report-button"
                    class="iawp-button favorite <?php echo $env->is_favorite('real-time') ? 'active' : ''; ?>"
                    data-controller="set-favorite-report"
                    data-set-favorite-report-type-value="real-time"
                    data-action="set-favorite-report#toggleFavoriteReport"
                    data-unfavorited-text="<?php echo esc_attr_e('Use as default report') ?>"
                    data-favorited-text="<?php echo esc_attr_e('Remove as default report') ?>"
            >
                <span class="dashicons dashicons-star-filled"></span>
            </button>
        </div>
    </div>


    <div id="real-time-dashboard" class="real-time-dashboard refreshed">
        <div class="real-time-grid">
            <div class="summary-container">
                <p class="summary-container-circle">
                    <span data-real-time-target="visitorMessage"
                          data-testid="real-time-title"><?php echo esc_html($visitors) ?></span>
                </p>

                <p class="summary-container-label">
                    <span><?php esc_html_e('Active Visitors', 'independent-analytics'); ?></span>
                    <button type="button" popovertarget="real-time-help">
                        <span class="dashicons dashicons-info-outline"></span>
                    </button>
                </p>


                <div id="real-time-help" popover class="real-time-help">
                    <p>
                        <?php esc_html_e("Active Visitors is the number of people who have viewed a page within the last 5 minutes", 'independent-analytics'); ?>
                    </p>
                </div>

                <div class="summary-filter" data-real-time-target="summaryFilter">
                    <div class="summary-no-filter">
                        <span class="dashicons dashicons-filter"></span> <?php esc_html_e('No filters applied', 'independent-analytics') ?>
                    </div>
                    <div class="summary-with-filter">
                        <span class="summary-with-filter-text">
                            <span class="dashicons dashicons-filter"></span>
                            <span data-real-time-target="summaryWithFilterText"></span>
                        </span>
                        <button class="remove-filter" data-action="real-time#removeFilter">
                            <span class="dashicons dashicons-no-alt"></span>
                            <span class="dashicons dashicons-update iawp-spin"></span>
                        </button>
                    </div>
                </div>

            </div>

            <div class="chart-container">
                <div class="chart-inner">
                    <div class="legend-container">
                        <h2 class="legend-title"><?php esc_html_e('Recent Activity', 'independent-analytics') ?></h2>
                        <div class="legend"></div>
                    </div>
                    <div class="chart-canvas-container">
                        <canvas data-real-time-target="realTimeChart"></canvas>
                    </div>
                </div>
            </div>

            <?php foreach ($lists as $list) : ?>
            <div class="most-popular-list"
                 data-list-id="<?php echo esc_attr($list['id']);  ?>"
                 data-group-id="<?php echo esc_attr($list['group_id']);  ?>"
                 data-real-time-target="<?php echo esc_attr($list['id']) ?>List"
                 data-testid="<?php echo esc_attr(sanitize_title($list['title'])); ?>">
                <div class="heading">
                        <?php echo $list['icon'] ?>
                    <span class="heading-title"><?php echo esc_html($list['title']) ?></span>
                        <?php if (count($list['groups']) === 1): ?>
                    <select name="" id=""></select>
                    <?php else: ?>
                    <select name="" id="" class="" data-action="real-time#changeGroup">
                            <?php foreach ($list['groups'] as $group): ?>
                        <option <?php selected($group[0], $list['group_id']) ?> value="<?php echo esc_attr($group[0]) ?>"><?php echo esc_html($group[1]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="subheading">
                    <span class="group-title"><?php echo $list['group_title'] ?></span>
                    <span class="group-count">(<?php echo $list['count'] ?>)</span>
                    <div class="group-column"><?php esc_html_e('Visitors', 'independent-analytics'); ?></div>
                </div>
                <ol class="short">
                        <?php foreach ($list['entries'] as $index => $item): ?>
                    <li data-id="<?php echo esc_attr($item['id']) ?>"
                        data-position="<?php echo esc_attr($index + 1); ?>"
                        data-group="<?php esc_attr_e($list['group_title']); ?>"
                        data-name="<?php esc_attr_e($item['title']); ?>"
                        class="<?php echo $item['blank'] ?? false ? 'is-blank' : '' ?>"
                    >
                        <span class="real-time-position"><?php echo absint($index + 1) ?></span>
                            <?php if (!empty($item['icon'])): ?>
                            <?php echo $item['icon'] ?>
                        <?php endif; ?>
                        <button class="list-item-filter" data-action="real-time#applyFilter">
                            <span class="list-item-filter-icons">
                                <span class="dashicons dashicons-filter"></span>
                                <span class="dashicons dashicons-remove"></span>
                                <span class="dashicons dashicons-update iawp-spin"></span>
                            </span>
                        </button>
                        <span class="real-time-resource">
                                <?php echo esc_html($item['title']) ?>
                                <?php if (!empty($item['subtitle'])): ?>
                                        <span class="real-time-subtitle"><?php echo esc_html($item['subtitle']); ?></span>
                                    <?php endif; ?>
                                </span>
                        <span class="real-time-stat"><?php echo $item['visitors'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <p class="most-popular-empty-message"><?php echo $list['empty']; ?></p>
            </div>
            <?php endforeach; ?>

            <div class="most-popular-list world-map"
                 data-testid="<?php // echo esc_attr(sanitize_title($list['title'])); ?>">
                <div class="heading">
                    <?php echo iawp_render('icons.geo') ?>
                    <span class="heading-title"><?php // echo esc_html($list['title']) ?></span>
                    <span class="heading-title">World Map</span>
                    <select></select>
                </div>
                <div class="real-time-map">
                    <div id="independent-analytics-chart"
                         data-controller="map"
                         data-map-data-value="<?php echo esc_attr(json_encode($country_data)) ?>"
                         data-map-flags-url-value="<?php echo iawp_url_to('/img/flags') ?>"
                         data-map-locale-value="<?php echo get_bloginfo('language') ?>"
                    >
                        <div data-map-target="chart"></div>
                    </div>
                </div>
                <p class="most-popular-empty-message <?php echo count($list['entries']) > 0 ? esc_attr('hide') : '' ?>"><?php esc_html_e('No results in the last 5 minutes.', 'independent-analytics') ?></p>
            </div>


        </div>
    </div>
</div>
