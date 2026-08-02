import { Controller } from "@hotwired/stimulus";
import { Chart, registerables } from "chart.js";
import htmlLegendPlugin from "../chart_plugins/html_legend_plugin";
import corsairPlugin from "../chart_plugins/corsair_plugin";
import { isDarkMode } from "../utils/appearance";
import { autoAnimate } from "@formkit/auto-animate";

Chart.register(...registerables);
Chart.defaults.font.family =
    '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"';

export default class extends Controller {
    static targets = [
        "pauseButton",
        "realTimeChart",
        "visitorMessage",
        "viewsList",
        "referrersList",
        "geoList",
        "campaignsList",
        "devicesList",
        "summaryFilter",
        "summaryWithFilterText",
    ];
    static values = {
        chartData: Object,
        nonce: String,
        locale: String,
        startingAt: Number,
        isTabVisible: {
            type: Boolean,
            default: true,
        },
        isLive: {
            type: Boolean,
            default: true,
        },
    };

    filter = null;

    pausedAt = null;

    connect() {
        document.addEventListener("visibilitychange", this.handleTabVisibilityChange);

        this.initializeChart();

        this.startInterval()
        this.restartProgressAnimation();

        autoAnimate(this.viewsListTarget);
        autoAnimate(this.referrersListTarget);
        autoAnimate(this.geoListTarget);
        autoAnimate(this.campaignsListTarget);
        autoAnimate(this.devicesListTarget);
    }

    disconnect() {
        document.removeEventListener("visibilitychange", this.handleTabVisibilityChange);
    }

    toggleLiveRefreshing() {
        this.isLiveValue = !this.isLiveValue;

        if (!this.isLiveValue) {
            this.stopServerPolling();
            this.pausedAt = this.startingAtValue;
            return;
        }

        this.pausedAt = null;
        this.startServerPolling();
    }

    startServerPolling() {
        this.pauseButtonTarget.classList.remove("paused");
        this.pauseButtonTarget.setAttribute("title", this.pauseButtonTarget.dataset.pauseTitle);
        this.refresh();
    }

    stopServerPolling() {
        clearInterval(this.interval);
        this.pauseButtonTarget.classList.add("paused");
        this.pauseButtonTarget.setAttribute("title", this.pauseButtonTarget.dataset.playTitle);
    }

    async changeGroup(event) {
        const select = event.currentTarget;
        const table = select.closest(".most-popular-list").dataset.listId;
        const group = select.value;

        select.setAttribute("disabled", "disabled");

        const data = {
            ...iawpActions.save_real_time_preferences,
            table,
            group,
        };

        this.disableUI();

        const response = await fetch(ajaxurl, {
            method: "POST",
            body: new URLSearchParams(data),
        });

        if (response.ok) {
            await this.refresh();
        }

        select.removeAttribute("disabled");
    }

    disableUI() {
        this.element.querySelectorAll(".most-popular-list li button").forEach((button) => {
            button.setAttribute("disabled", "disabled");
        });

        this.element.querySelectorAll(".most-popular-list .heading select").forEach((select) => {
            select.setAttribute("disabled", "disabled");
        });

        // TODO Where does this safely get reenabled?
        // this.element.querySelector('.remove-filter')?.setAttribute('disabled', 'disabled');
    }

    applyFilter(event) {
        const buttonEl = event.currentTarget;
        const itemEl = buttonEl.closest("li");
        const listEl = buttonEl.closest(".most-popular-list");

        if (itemEl.classList.contains("is-current-filter")) {
            this.filter = null;
        } else {
            this.filter = {
                listId: listEl.dataset.listId,
                groupId: listEl.dataset.groupId,
                rowId: itemEl.dataset.id,
                group: itemEl.dataset.group,
                name: itemEl.dataset.name,
            };
        }

        buttonEl.closest("li").classList.add("is-applying-filter");
        buttonEl.setAttribute("disabled", "disabled");

        this.disableUI();
        this.refresh();
    }

    async removeFilter(event) {
        const button = event.currentTarget;

        button.classList.add("is-removing");
        this.filter = null;

        this.disableUI();
        await this.refresh();

        button.classList.remove("is-removing");
    }

    async refresh() {
        clearInterval(this.interval);

        const data = {
            ...iawpActions.fetch_real_time_data,
        };

        const filter = this.filter;

        if (filter) {
            data.listId = filter.listId;
            data.groupId = filter.groupId;
            data.rowId = filter.rowId;
        }

        if (Number.isInteger(this.pausedAt)) {
            data.startingAt = this.pausedAt;
        }

        const response = await fetch(ajaxurl, {
            method: "POST",
            body: new URLSearchParams(data),
        });

        if (response.ok) {
            const { data } = await response.json();
            data.filter = filter;
            this.rerender(data);
        }

        if (!this.pausedAt) {
            this.startInterval()
        }
    }

    startInterval() {
        clearInterval(this.interval);
        this.interval = setInterval(() => {
            this.refresh();
        }, 10000);
    }

    handleTabVisibilityChange = () => {
        this.isTabVisibleValue = !document.hidden;

        if (!this.isTabVisibleValue || !this.isLiveValue) {
            this.stopServerPolling();
            return;
        }

        this.startServerPolling();
    };

    rerender(data) {
        this.visitorMessageTarget.textContent = data.visitors;
        this.startingAtValue = data.starting_at;

        if (data.filter) {
            this.summaryWithFilterTextTarget.textContent = data.filter.group + ": " + data.filter.name;
            this.summaryFilterTarget.classList.add("has-filter");
        } else {
            this.summaryFilterTarget.classList.remove("has-filter");
        }

        this.restartProgressAnimation();
        this.updateChart(data.chart_data, { animate: true });
        this.updateMap(data.country_data);

        this.updateList({
            list: this.viewsListTarget,
            data: data.lists.views,
            filter: data.filter,
        });

        this.updateList({
            list: this.referrersListTarget,
            data: data.lists.referrers,
            filter: data.filter,
        });

        this.updateList({
            list: this.geoListTarget,
            data: data.lists.geo,
            filter: data.filter,
        });

        this.updateList({
            list: this.campaignsListTarget,
            data: data.lists.campaigns,
            filter: data.filter,
        });

        this.updateList({
            list: this.devicesListTarget,
            data: data.lists.devices,
            filter: data.filter,
        });
    }

    updateList({ list, data, filter }) {
        list.querySelector(".group-title").textContent = data.group_title;
        list.querySelector(".group-count").textContent = "(" + data.count + ")";
        list.dataset.groupId = data.group_id;

        list.querySelectorAll("li.is-current-filter").forEach((element) => {
            element.classList.remove("is-current-filter");
            element.classList.remove("is-applying-filter");
        });

        const element = list.querySelector("ol");
        const entries = data.entries;

        // Remove entries that no longer exist
        const entryIds = new Set(entries.map((entry) => String(entry.id)));
        const existingItems = new Map(Array.from(element.querySelectorAll("li")).map((li) => [String(li.dataset.id), li]));

        const isEmpty = entries.every((entry) => entry.blank);
        element.closest(".most-popular-list").querySelector(".most-popular-empty-message").classList.toggle("show", isEmpty);

        existingItems.forEach((li, id) => {
            if (!entryIds.has(id)) {
                li.remove();
                existingItems.delete(id);
            }
        });

        // Add entries that are not there
        entries.forEach((entry) => {
            const id = String(entry.id);

            if (existingItems.has(id)) {
                return;
            }

            const entryElement = this.elementFromEntry(entry);
            element.appendChild(entryElement);
            existingItems.set(id, entryElement);
        });

        // Update existing entries that were there and are still there
        entries.forEach((entry) => {
            const id = String(entry.id);
            const existingItem = existingItems.get(id);
            const entryElement = this.elementFromEntry(entry);

            existingItem.dataset.id = entryElement.dataset.id;
            existingItem.dataset.position = entryElement.dataset.position;
            existingItem.dataset.group = entryElement.dataset.group;
            existingItem.dataset.name = entryElement.dataset.name;
            existingItem.replaceChildren(...entryElement.childNodes);
        });

        // Reorder final list using moveBefore
        Array.from(element.querySelectorAll("li"))
            .sort((a, b) => Number(a.dataset.position) - Number(b.dataset.position))
            .forEach((li, index) => {
                const currentItem = element.children[index];

                if (li === currentItem) {
                    return;
                }

                element.moveBefore(li, currentItem);
            });

        if (filter && data.id == filter.listId && data.group_id == filter.groupId) {
            const element = list.querySelector(`li[data-id="${this.filter.rowId}"]`);

            if (element) {
                element.classList.remove("is-applying-filter");
                element.classList.add("is-current-filter");
                element.removeAttribute("disabled");
            }
        }

        const hasFilter = list.querySelector("li.is-current-filter");

        list.querySelectorAll("select").forEach((element) => {
            element.toggleAttribute("disabled", hasFilter);
        });

        if (filter) {
            element.scrollTo(0, 0);
        }
    }

    getLocale() {
        // Validate the locale
        try {
            new Intl.NumberFormat(this.localeValue);

            return this.localeValue;
        } catch (e) {
            return "en-US";
        }
    }

    elementFromEntry(entry) {
        const id = entry["id"];
        const position = entry["position"];
        const title = entry["title"];
        const group = entry["group"];
        const subtitle = entry["subtitle"];
        const visitors = entry["visitors"];
        const icon = entry["icon"] ? entry["icon"] : "";
        const subtitleHTML = subtitle ? `<span class="real-time-subtitle">${subtitle}</span>` : "";
        const className = (entry["blank"] ?? false) ? "is-blank" : "";

        const li = `
            <li data-id="${id}" data-position="${position}" data-group="${group}" data-name="${title}" class="${className}">
                <span class="real-time-position">${position}</span>
                <button class="list-item-filter" data-action="real-time#applyFilter">
                    <span class="list-item-filter-icons">
                        <span class="dashicons dashicons-filter"></span>
                        <span class="dashicons dashicons-remove"></span>
                        <span class="dashicons dashicons-update iawp-spin"></span>
                    </span>
                </button>
                ${icon}
                <span class="real-time-resource">${title} ${subtitleHTML}</span>
                <span class="real-time-stat">${visitors}</span>
            </li>
        `;
        const el = document.createElement("div");
        el.innerHTML = li;
        return el.firstElementChild;
    }

    getDatasetData(id) {
        const timestamps = this.chartDataValue["timestamps"];
        const values = this.chartDataValue[id];

        return timestamps.map((timestamp, index) => {
            return { x: index, y: values[index], timestamp };
        });
    }

    initializeChart() {
        const element = this.realTimeChartTarget;

        const datasetOptions = {
            borderWidth: {
                bottom: 1,
                top: 1,
                left: 1,
                right: 1,
            },
            borderRadius: 2,
        };

        const data = {
            labels: this.chartDataValue["long_labels"],
            datasets: [
                {
                    id: "views",
                    label: iawpText.views,
                    data: this.getDatasetData("views"),
                    backgroundColor: isDarkMode() ? "#A17FDC" : "rgba(81,35,160,0.6)",
                    borderColor: isDarkMode() ? "#A17FDC" : "rgba(81,35,160,0.6)",
                    ...datasetOptions,
                },
                {
                    id: "orders",
                    label: iawpText.orders,
                    data: this.getDatasetData("orders"),
                    backgroundColor: isDarkMode() ? "#68D08F" : "rgba(35, 125, 68, 0.6)",
                    borderColor: isDarkMode() ? "#68D08F" :"rgba(35, 125, 68, 0.6)",
                    ...datasetOptions,
                },
                {
                    id: "clicks",
                    label: iawpText.clicks,
                    data: this.getDatasetData("clicks"),
                    backgroundColor: isDarkMode() ? "#61B7F1" : "rgba(52, 152, 219, 0.6)",
                    borderColor: isDarkMode() ? "#61B7F1" : "rgba(52, 152, 219, 0.6)",
                    ...datasetOptions,
                },
                {
                    id: "form-submissions",
                    label: iawpText.formSubmissions,
                    data: this.getDatasetData("form_submissions"),
                    backgroundColor: isDarkMode() ? "#FFDF6F" : "hsla(46, 100%, 63%, 0.60)",
                    borderColor: isDarkMode() ? "#FFDF6F" : "rgba(255, 212, 64, 0.6)",
                    ...datasetOptions,
                },
            ],
        };

        const config = {
            type: "bar",
            data,
            options: {
                locale: this.getLocale(),
                animation: {
                    duration: 0,
                },
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: "index",
                },
                transitions: {
                    realTimeShift: {
                        animations: {
                            numbers: {
                                type: "number",
                                properties: ["x"],
                                duration: 450,
                                easing: "easeOutQuart",
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        stacked: true,
                        border: {
                            color: "#DEDAE6",
                            dash: [2, 4],
                        },
                        grid: {
                            tickColor: "#DEDAE6",
                            display: true,
                            drawOnChartArea: true,
                        },
                        beginAtZero: true,
                        suggestedMax: 5,
                        ticks: {
                            color: isDarkMode() ? "#ffffff" : "#6D6A73",
                            precision: 0,
                        },
                    },
                    x: {
                        type: "linear",
                        min: -0.5,
                        max: this.chartDataValue["timestamps"].length - 0.5,
                        stacked: true,
                        border: {
                            color: "#DEDAE6",
                        },
                        grid: {
                            tickColor: "#DEDAE6",
                            display: true,
                            drawOnChartArea: false,
                        },
                        ticks: {
                            color: isDarkMode() ? "#ffffff" : "#6D6A73",
                            autoSkip: false,
                            beginAtZero: true,
                            stepSize: 1,
                            callback: (value) => {
                                return this.chartDataValue["short_labels"][value] ?? "";
                            },
                        },
                        afterBuildTicks: (axis) => {
                            axis.ticks = this.chartDataValue["timestamps"].map((timestamp, index) => ({ value: index }));
                        },
                    },
                },
                plugins: {
                    mode: String, // 'light' or 'dark'
                    htmlLegend: {
                        container: element.closest(".chart-container").querySelector(".legend"),
                        shouldAnimate: false,
                    },
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                return this.chartDataValue["long_labels"][items[0].raw.x] ?? "";
                            },
                        },
                    },
                    corsair: {
                        dash: [2, 4],
                        color: "#777",
                        width: 1,
                    },
                },
            },
            plugins: [corsairPlugin, htmlLegendPlugin],
        };

        new Chart(element, config);
    }

    updateChart(chartData, { animate = false } = {}) {
        const element = this.realTimeChartTarget;
        const chart = Chart.getChart(element);
        const shouldAnimate = animate && this.prepareChartShiftAnimation(chart, this.chartDataValue, chartData);

        this.chartDataValue = chartData;
        chart.data.labels.splice(0, chart.data.labels.length, ...chartData["long_labels"]);
        chart.options.scales.x.max = chartData["timestamps"].length - 0.5;

        chart.data.datasets.forEach((dataset) => {
            const dataKey = {
                views: "views",
                orders: "orders",
                clicks: "clicks",
                "form-submissions": "form_submissions",
            }[dataset.id];

            if (dataKey) {
                const nextData = this.getDatasetData(dataKey);

                if (dataset.data.length !== nextData.length) {
                    dataset.data.splice(0, dataset.data.length, ...nextData);
                    return;
                }

                nextData.forEach((dataPoint, index) => {
                    Object.assign(dataset.data[index], dataPoint);
                });
            }
        });

        chart.update(shouldAnimate ? "realTimeShift" : "none");
    }

    prepareChartShiftAnimation(chart, previousChartData, nextChartData) {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return false;
        }

        if (
            !Array.isArray(previousChartData?.timestamps) ||
            !Array.isArray(nextChartData?.timestamps) ||
            previousChartData.timestamps.length !== nextChartData.timestamps.length
        ) {
            return false;
        }

        const shiftCount = Math.round((nextChartData.timestamps[0] - previousChartData.timestamps[0]) / 10);

        if (shiftCount < 1 || shiftCount >= nextChartData.timestamps.length) {
            return false;
        }

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            const previousElements = meta.data.map((element) => ({
                x: element.x,
                y: element.y,
                base: element.base,
                width: element.width,
                height: element.height,
            }));
            const newestPreviousElement = previousElements[previousElements.length - 1];
            const base = chart.scales.y.getBasePixel();
            const enterFromX = chart.chartArea.right + (newestPreviousElement?.width ?? 0);

            nextChartData.timestamps.forEach((timestamp, index) => {
                const previousIndex = index + shiftCount;

                if (previousIndex < previousElements.length) {
                    Object.assign(meta.data[index], previousElements[previousIndex]);
                    return;
                }

                Object.assign(meta.data[index], {
                    x: enterFromX,
                    y: base,
                    base,
                    width: newestPreviousElement?.width ?? meta.data[index].width,
                    height: 0,
                });
            });
        });

        return true;
    }

    updateMap(countryData) {
        document.querySelector('[data-controller="map"]').dataset.mapDataValue = JSON.stringify(countryData);
    }

    restartProgressAnimation() {
        const animation = this.element.querySelector(".real-time-progress-bar")?.getAnimations().at(0);

        if (!animation) {
            return;
        }

        animation.cancel();
        animation.play();
    }
}
