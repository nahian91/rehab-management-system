jQuery(document).ready(function($) {

    // // Helper function to safely initialize DataTables
    // function initAfonDataTable(selector, options) {
    //     var $target = $(selector);
    //     if ($target.length) {
    //         // If already initialized, destroy or return
    //         if ($.fn.DataTable.isDataTable(selector)) {
    //             return; 
    //         }
    //         $target.DataTable($.extend({
    //             retrieve: true,
    //             language: {
    //                 search: "",
    //                 paginate: { next: '→', previous: '←' }
    //             },
    //             dom: '<"top"f>rt<"bottom"ip><"clear">'
    //         }, options));
    //     }
    // }

    // /* --- 1. Initialize All Tables --- */

    // initAfonDataTable('#afon-users-directory-table', {
    //     pageLength: 20,
    //     language: { searchPlaceholder: "Search by name or email..." }
    // });

    // initAfonDataTable('#afon-customer-orders-table', { 
    //     pageLength: 10, 
    //     dom: 'rtip' 
    // });

    // initAfonDataTable('#afon-reports-table', {
    //     pageLength: 10,
    //     language: { searchPlaceholder: "Search transactions..." }
    // });

    // initAfonDataTable('#afon-extras-directory-table', {
    //     pageLength: 10,
    //     language: { searchPlaceholder: "Search toppings..." },
    //     columnDefs: [{ orderable: false, targets: [0, 4] }] // Photo and Management
    // });    
});