// Data Table
// let table = new DataTable('#myTable', {
//     "paging": false,
//     "info": false,
//     "order": [],
//     "columnDefs": [
//         { "orderable": false, "targets": -1 } 
//     ],
//     language: {
//         search: translations.search
//     }
// });
$(document).ready(function () {
    $('#myTable').DataTable({
        paging: false,
        info: false,
        order: [],
        autoWidth: false,
        responsive: true,
        columnDefs: [
            { orderable: false, targets: -1 },
            { defaultContent: "-", targets: "_all" }
        ],
        language: {
            search: translations.search || "Search:"
        }
    });
});




