$(window).load(function() {
  window.pace_widget = {
    init: function() {
      console.log($("#single-widget").length);
      if ($("#single-widget").length) {
        setTimeout(this.show_single_widget, 2000);
      }

      if ($("#multiple-widget").length) {
        setTimeout(this.show_multiple_widget, 2000);
      }

      // woocommerce checkout update order trigger
      $(document.body).on(
        "#collapse-checkout-confirm",
        this.show_checkout_widget
      );

      // pace show checkout widgets
    },
    /**
     * Show pace single widget
     *
     * @version 1.0.0
     */
    show_single_widget: function() {
      var data = $("#single-widget").data();
      if (data.enable) {
        pacePay.loadWidgets({
          containerSelector: "#single-widget",
          type: "single-product",
          styles: {
            logoTheme: data.themeColor == 1 ? "dark" : "light",
            textPrimaryColor: data.primary,
            textSecondaryColor: data.second,
            fontSize: (data.fontsize ? data.fontsize : 13) + "px"
          }
        });
      }
    },

    /**
     * Show pace multiple widget
     *
     * @version 1.0.0
     */
    show_multiple_widget: function() {
      var data = $("#multiple-widget").data();
      if (data.enable) {
        pacePay.loadWidgets({
          containerSelector: "#multiple-widget",
          type: "multi-products",
          styles: {
            logoTheme: data.themeColor == 1 ? "dark" : "light",
            textColor: data.primary,
            fontSize: (data.fontsize ? data.fontsize : 13) + "px"
          }
        });
      }
    },

    /**
     * Show pace checkout widget
     *
     * @version 1.0.0
     */
    show_checkout_widget: function() {
      var data = $("#pace-pay-widget-container").data();

      if (data === undefined) {
        return;
      }
      // debugger;
      if (data.enabled) {
        pacePay.loadWidgets({
          containerSelector: "#pace-pay-widget-container",
          type: "checkout",
          styles: {
            textPrimaryColor: data.primary,
            textSecondaryColor: data.second,
            timelineColor: data.timeline,
            backgroundColor: data.background,
            foregroundColor: data.foreground,
            fontSize: data.fontsize
          }
        });
      }
    },

    isChosen: function() {
      return $(document.body)
        .find("#payment_method_pace")
        .is(":checked");
    }
  };
  window.pace_widget.init();
});
