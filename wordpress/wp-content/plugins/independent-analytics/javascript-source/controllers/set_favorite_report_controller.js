import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        id: String,
        type: String,
    };

    connect() {
        this.updateButtonTitle();
    }

    toggleFavoriteReport(event) {
        const shouldBeFavorited = this.element.classList.contains("active") === false;

        if (shouldBeFavorited) {
            this.favoriteReport(event);
        } else {
            this.unfavoriteReport(event);
        }
    }

    favoriteReport() {
        this.element.classList.add("active");

        const data = {
            ...iawpActions.set_favorite_report,
            id: this.idValue,
            type: this.typeValue,
        };

        jQuery
            .post(ajaxurl, data, (response) => {
                this.updateButtonTitle();
                this.removeExistingStar();

                if (this.idValue) {
                    this.markSavedReportAsFavorite(this.idValue);
                } else {
                    this.markBaseReportAsFavorite(this.typeValue);
                }
            })
            .fail(() => {
                this.element.classList.remove("active");
            });
    }

    unfavoriteReport(event) {
        this.element.classList.remove("active");
        event.currentTarget.blur();

        const data = {
            ...iawpActions.clear_favorite_report,
        };

        jQuery
            .post(ajaxurl, data, (response) => {
                this.updateButtonTitle();
                this.removeExistingStar();
            })
            .fail(() => {
                this.element.classList.add("active");
            });
    }

    updateButtonTitle() {
        if (this.element.classList.contains("active")) {
            this.element.setAttribute("title", this.element.dataset.favoritedText);
        } else {
            this.element.setAttribute("title", this.element.dataset.unfavoritedText);
        }
    }

    removeExistingStar() {
        const favoriteReports = Array.from(document.querySelectorAll("[data-report-id].favorite, [data-report-type].favorite"));

        favoriteReports.forEach((savedReport) => {
            savedReport.classList.remove("favorite");
        });
    }

    markSavedReportAsFavorite(id) {
        const element = document.querySelector(`[data-report-id="${id}"]`);

        if (element) {
            element.classList.add("favorite");
        }
    }

    markBaseReportAsFavorite(type) {
        const element = document.querySelector(`[data-report-type="${type}"]`);

        if (element) {
            element.classList.add("favorite");
        }
    }
}
