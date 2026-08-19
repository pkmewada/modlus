
// =========================================
// API
// =========================================

const API = {

    getSummary:
        API_BASE +
        '/getAttendanceManagementSummary.php',

    getListing:
        API_BASE +
        '/getAttendanceManagementListing.php',

    getBreakHistory:
        API_BASE +
        '/getAttendanceBreakHistoryAdmin.php',

    getAttendanceDetails:
        API_BASE +
        '/getAttendanceDetails.php',

    updateAttendance:
        API_BASE +
        '/updateAttendance.php',
        
    getAttendanceAnalytics:
        API_BASE +
        '/getEmployeeAttendanceAnalytics.php'
};

// =========================================
// GLOBALS
// =========================================

let attendanceTable = null;

let attendanceRows = [];



// =========================================
// FILTER VALUES
// =========================================

let selectedEmployee = '';

let selectedStatus = '';

let selectedDate = '';





// =========================================
// INIT
// =========================================

$(function() {

    loadAttendanceSummary();

    loadAttendanceListing();

});

// =========================================
// LOAD SUMMARY
// =========================================

function loadAttendanceSummary()
{
    $.getJSON(

        API.getSummary,

        {
            date: selectedDate || ''
        },

        function(response)
        {
            if (!response.success) {

                return showToast(
                    'error',
                    response.message
                );
            }

            renderSummaryCards(
                response.data
            );
        }

    ).fail(function() {

        showToast(
            'error',
            'Failed to load attendance summary'
        );

    });
}


$(function () {

    // Set today's date in YYYY-MM-DD format
    selectedDate = new Date().toISOString().split('T')[0];

    // Set the date picker value
    $('#attendanceDateFilter').val(selectedDate);

    loadAttendanceSummary();

    loadAttendanceListing();
});


// =========================================
// LOAD ATTENDANCE LISTING
// =========================================

function loadAttendanceListing()
{
    $.getJSON(

        API.getListing,

        {
            date: selectedDate || ''
        },

        function(response)
        {
            if (!response.success) {

                return showToast(
                    'error',
                    response.message
                );
            }

            attendanceRows =
                response.data || [];

            populateEmployeeFilter(
                attendanceRows
            );

            renderAttendanceTable(
                attendanceRows
            );
        }

    ).fail(function() {

        showToast(
            'error',
            'Failed to load attendance records'
        );

    });
}
// =========================================
// SUMMARY CARDS
// =========================================

function renderSummaryCards(data)
{
    $('#presentTodayCard').text(
        data.presentToday || 0
    );

    $('#halfDayCard').text(
        data.halfDay || 0
    );

    $('#absentTodayCard').text(
        data.absent || 0
    );

    $('#activeTodayCard').text(
        data.activeToday || 0
    );
}

// =========================================
// EMPLOYEE FILTER
// =========================================

function populateEmployeeFilter(rows)
{
    const employees = {};

    rows.forEach(function(row) {

        employees[
            row.employeeId
        ] = row.fullName;
    });

    let html = `
        <option value="">
            All Employees
        </option>
    `;
    
    let analyticsOptions =
        '<option value="">Select Employee</option>';

    Object.entries(employees)
    .forEach(function(item) {

        const option = `
            <option value="${item[0]}">
                ${item[1]}
            </option>
        `;

        html += option;

        analyticsOptions += option;
    });

    $('#employeeFilter')
        .html(html);
        
    $('#analyticsEmployeeId')
    .html(
        analyticsOptions
    );
}

// =========================================
// TABLE
// =========================================

function renderAttendanceTable(rows)
{
    let html = '';

    if (!rows.length) {

        html = `
            <tr>

                <td
                    colspan="9"
                    class="text-center text-muted"
                >

                    No attendance records found

                </td>

            </tr>
        `;
    }
    else {

        rows.forEach(function(row, index) {

            html += `
                <tr>

                    <td>
                        ${index + 1}
                    </td>

                    <td>

                        <div>

                            <div
                                class="fw-semibold"
                            >
                                ${row.fullName || '--'}
                            </div>

                            <div
                                class="small text-muted"
                            >
                                ${row.employeeCode || '--'}
                            </div>

                            <div
                                class="small text-muted"
                            >
                                ${row.designationName || '--'}
                            </div>

                        </div>

                    </td>

                    <td>
                        ${formatDate(
                            row.attendanceDate
                        )}
                    </td>

                    <td>
                        ${formatTime(
                            row.punchInTime
                        )}
                    </td>

                    <td>
                        ${
                            row.punchOutTime
                                ? formatTime(
                                    row.punchOutTime
                                )
                                : '--'
                        }
                    </td>

                    <td>
                        ${formatDuration(
                            row.totalWorkingSeconds
                        )}
                    </td>

                    <td>
                        ${formatDuration(
                            row.totalBreakSeconds
                        )}
                    </td>

                    <td>
                        ${getStatusBadge(
                            row.attendanceStatus
                        )}
                    </td>

                    <td>

                        <div
                            class="btn-list"
                        >

                            <button
                                class="btn btn-sm btn-outline-primary view-breaks"
                                data-id="${row.id}"
                            >
                                Breaks
                            </button>

                            <button
                                class="btn btn-sm btn-outline-warning edit-attendance"
                                data-id="${row.id}"
                            >
                                Edit
                            </button>

                        </div>

                    </td>

                </tr>
            `;
        });
    }

    if (
        $.fn.DataTable.isDataTable(
            '#attendanceTable'
        )
    ) {

        attendanceTable.destroy();
    }

    $('#attendanceTable tbody')
        .html(html);

    attendanceTable =
        $('#attendanceTable')
        .DataTable({

            order: [],

            pageLength: 10,

            drawCallback: function() {

                let api =
                    this.api();

                api.column(
                    0,
                    {
                        search: 'applied',
                        order: 'applied'
                    }
                )
                .nodes()
                .each(function(
                    cell,
                    i
                ) {

                    cell.innerHTML =
                        i + 1;

                });
            },

            dom:
                "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",

            columnDefs: [

                {
                    targets: [0],

                    searchable: false,

                    orderable: false
                }
            ]
        });
        
        
        
        // =========================================
    // CUSTOM FILTERS
    // =========================================
    
    $.fn.dataTable.ext.search = [];
    
    
    
    $.fn.dataTable.ext.search.push(
    
        function(settings, data, dataIndex)
        {
            const row =
                attendanceRows[dataIndex];
    
            if (!row) {
                return true;
            }
    
            // Employee Filter
            if (
                selectedEmployee &&
                row.employeeId != selectedEmployee
            ) {
                return false;
            }
    
            // Status Filter
            if (
                selectedStatus &&
                row.attendanceStatus !== selectedStatus
            ) {
                return false;
            }
    
            // Date Filter
            if (
                selectedDate &&
                row.attendanceDate !== selectedDate
            ) {
                return false;
            }
    
            return true;
        }
    );
        
}

// =========================================
// STATUS BADGE
// =========================================

function getStatusBadge(status)
{
    status =
        (status || '')
        .toLowerCase();

    switch (status) {

        case 'present':

            return `
                <span
                    class="badge bg-success"
                >
                    Present
                </span>
            `;

        case 'half_day':

            return `
                <span
                    class="badge bg-warning"
                >
                    Half Day
                </span>
            `;

        case 'absent':

            return `
                <span
                    class="badge bg-danger"
                >
                    Absent
                </span>
            `;

        case 'in_progress':

            return `
                <span
                    class="badge bg-primary"
                >
                    In Progress
                </span>
            `;

        default:

            return `
                <span
                    class="badge bg-secondary"
                >
                    ${status}
                </span>
            `;
    }
}

// =========================================
// DATE
// =========================================

function formatDate(date)
{
    if (!date) {

        return '--';
    }

    return new Date(date)
        .toLocaleDateString(
            'en-IN',
            {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }
        );
}

// =========================================
// TIME
// =========================================

function formatTime(time)
{
    if (!time) {

        return '--';
    }

    const date =
        new Date(
            '1970-01-01T' + time
        );

    return date.toLocaleTimeString(
        'en-IN',
        {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        }
    );
}

// =========================================
// DURATION
// =========================================

function formatDuration(seconds)
{
    seconds =
        parseInt(seconds || 0);

    if (seconds < 60) {

        return seconds + ' Sec';
    }

    if (seconds < 3600) {

        return Math.floor(
            seconds / 60
        ) + ' Min';
    }

    const hours =
        Math.floor(
            seconds / 3600
        );

    const minutes =
        Math.floor(
            (seconds % 3600) / 60
        );

    return (
        hours +
        'h ' +
        minutes +
        'm'
    );
}

// =========================================
// SEARCH
// =========================================

$('#attendanceTableSearch').on(
    'keyup',
    function()
    {
        if (
            attendanceTable
        ) {

            attendanceTable
                .search(
                    this.value
                )
                .draw();
        }
    }
);

// =========================================
// STATUS FILTER
// =========================================

$('#statusFilter').on(
    'change',
    function()
    {
        selectedStatus =
            $(this).val();

        if (attendanceTable) {

            attendanceTable.draw();
        }
    }
);

// =========================================
// EMPLOYEE FILTER
// =========================================

$('#employeeFilter').on(
    'change',
    function()
    {
        selectedEmployee =
            $(this).val();

        if (attendanceTable) {

            attendanceTable.draw();
        }
    }
);

// =========================================
// DATE FILTER
// =========================================

$('#attendanceDateFilter').on(
    'change',
    function()
    {
        selectedDate = this.value;

        loadAttendanceSummary();

        loadAttendanceListing();
    }
);

// =========================================
// REFRESH
// =========================================

$('#refreshAttendanceBtn').on(
    'click',
    function()
    {
        loadAttendanceSummary();

        loadAttendanceListing();

        showToast(
            'success',
            'Attendance refreshed'
        );
    }
);


// =========================================
// VIEW BREAKS
// =========================================

$(document).on(

    'click',

    '.view-breaks',

    function()
    {
        const attendanceId =
            $(this).data('id');

        loadBreakHistory(
            attendanceId
        );
    }
);

// =========================================
// LOAD BREAK HISTORY
// =========================================

function loadBreakHistory(
    attendanceId
)
{
    $.getJSON(

        API.getBreakHistory,

        {
            attendanceId:
                attendanceId
        },

        function(response)
        {
            if (
                !response.success
            ) {

                return showToast(
                    'error',
                    response.message
                );
            }

            renderBreakHistory(
                response.data || []
            );

            renderBreakSummary(
                response.summary || {}
            );

            $('#attendanceBreakModal')
                .modal('show');
        }

    ).fail(function() {

        showToast(
            'error',
            'Failed to load break history'
        );

    });
}

// =========================================
// BREAK HISTORY
// =========================================

function renderBreakHistory(rows)
{
    let html = '';

    if (!rows.length) {

        html = `
            <tr>

                <td
                    colspan="4"
                    class="text-center text-muted"
                >
                    No break records found
                </td>

            </tr>
        `;
    }
    else {

        rows.forEach(function(row) {

            html += `
                <tr>

                    <td>
                        ${row.breakName}
                    </td>

                    <td>
                        ${formatTime(
                            row.breakStartTime
                        )}
                    </td>

                    <td>
                        ${
                            row.breakEndTime
                                ? formatTime(
                                    row.breakEndTime
                                )
                                : '--'
                        }
                    </td>

                    <td>
                        ${formatDuration(
                            row.breakDurationSeconds
                        )}
                    </td>

                </tr>
            `;
        });
    }

    $('#breakHistoryTableBody')
        .html(html);
}

// =========================================
// BREAK SUMMARY
// =========================================

function renderBreakSummary(summary)
{
    let html = '';

    Object.entries(summary)
        .forEach(function(
            [name, seconds]
        ) {

            html += `
                <div
                    class="d-flex justify-content-between
                           align-items-center
                           border rounded
                           px-3 py-2 mb-2"
                >

                    <span
                        class="fw-semibold"
                    >
                        ${name}
                    </span>

                    <span
                        class="badge bg-success"
                    >
                        ${formatDuration(
                            seconds
                        )}
                    </span>

                </div>
            `;
        });

    $('#breakSummaryContainer')
        .html(html);
}

$(document).on(
    'click',
    '.edit-attendance',
    function() {

        loadAttendanceDetails(
            $(this).data('id')
        );
    }
);

// =========================================
// LOAD ATTENDANCE DETAILS
// =========================================

function loadAttendanceDetails(
    attendanceId
)
{
    $.getJSON(

        API.getAttendanceDetails,

        {
            attendanceId:
                attendanceId
        },

        function(response)
        {
            if (
                !response.success
            ) {

                return showToast(
                    'error',
                    response.message
                );
            }

            populateAttendanceModal(
                response.data
            );

            $('#editAttendanceModal')
                .modal('show');
        }

    ).fail(function() {

        showToast(
            'error',
            'Failed to load attendance details'
        );

    });
}

// =========================================
// POPULATE ATTENDANCE MODAL
// =========================================

function populateAttendanceModal(data)
{
    $('#attendanceId').val(
        data.id || ''
    );

    $('#employeeName').val(
        data.fullName || ''
    );

    $('#attendanceDate').val(
        data.attendanceDate || ''
    );

    $('#punchInTime').val(
        data.punchInTime || ''
    );

    $('#punchOutTime').val(
        data.punchOutTime || ''
    );

    $('#attendanceStatus').val(
        data.attendanceStatus || ''
    );

    $('#remarks').val(
        data.remarks || ''
    );
}

// =========================================
// UPDATE ATTENDANCE
// =========================================

$('#editAttendanceForm').on(
    'submit',
    function(e)
    {
        e.preventDefault();

        submitAttendanceUpdate();
    }
);

function submitAttendanceUpdate()
{
    $.ajax({

        url:
            API.updateAttendance,

        type: 'POST',

        dataType: 'json',

        data:
            $('#editAttendanceForm')
            .serialize(),

        success: function(response)
        {
            if (!response.success) {

                return showToast(
                    'error',
                    response.message
                );
            }

            showToast(
                'success',
                response.message
            );

            bootstrap.Modal
                .getInstance(
                    document.getElementById(
                        'editAttendanceModal'
                    )
                )
                ?.hide();

            loadAttendanceListing();

            loadAttendanceSummary();
        },

        error: function()
        {
            showToast(
                'error',
                'Failed to update attendance'
            );
        }
    });
}


let analyticsChart = null;

$('#generateAnalyticsBtn').on(

    'click',

    function()
    {
        loadAttendanceAnalytics();
    }
);

function loadAttendanceAnalytics()
{
    $.getJSON(

        API.getAttendanceAnalytics,

        {
            employeeId:
                $('#analyticsEmployeeId').val(),

            fromDate:
                $('#analyticsFromDate').val(),

            toDate:
                $('#analyticsToDate').val()
        },

        function(response)
        {
            if (
                !response.success
            ) {

                return showToast(
                    'error',
                    response.message
                );
            }

            renderAttendanceAnalytics(
                response
            );
        }
    );
}


function renderAttendanceAnalytics(
    response
)
{
    const rows =
        response.data || [];

    const summary =
        response.summary || {};

    const dates = [];

    const working = [];

    const breaks = [];

    rows.forEach(function(row) {

        dates.push(
            row.attendanceDate
        );

        working.push(
            (
                row.totalWorkingSeconds /
                3600
            ).toFixed(2)
        );

        breaks.push(
            (
                row.totalBreakSeconds /
                3600
            ).toFixed(2)
        );
    });

    if (
        analyticsChart
    ) {

        analyticsChart.destroy();
    }

    analyticsChart =
    new ApexCharts(

        document.querySelector(
            '#attendanceAnalyticsChart'
        ),

        {

            chart: {
                type: 'line',
                height: 400,
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false
                }
            },

            colors: [
                '#0cd7b1', // Working Hours
                '#d77cf7'  // Break Hours
            ],

            series: [

                {
                    name: 'Working Hours',
                    data: working
                },

                {
                    name: 'Break Hours',
                    data: breaks
                }

            ],

            stroke: {
                curve: 'smooth',
                width: 2
            },

            markers: {
                size: 0,
                hover: {
                    size: 6
                }
            },

            dataLabels: {
                enabled: false
            },

            fill: {
                type: 'solid',
                opacity: 1
            },

            grid: {
                borderColor: '#f1f1f1',
                strokeDashArray: 4
            },

            legend: {
                position: 'top',
                horizontalAlign: 'center'
            },

            tooltip: {
                shared: true,
                intersect: false
            },

            xaxis: {

                categories: dates,

                labels: {
                    rotate: -45
                }

            },

            yaxis: {

                title: {
                    text: 'Hours'
                },

                min: 0

            }

        }
    );

    analyticsChart.render();

    $('#analyticsSummary').html(`

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Present Days
                    </h6>

                    <h3>
                        ${summary.presentDays}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Half Days
                    </h6>

                    <h3>
                        ${summary.halfDays}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Working Hours
                    </h6>

                    <h3>
                        ${(summary.workingSeconds / 3600).toFixed(1)}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Break Hours
                    </h6>

                    <h3>
                        ${(summary.breakSeconds / 3600).toFixed(1)}
                    </h3>

                </div>

            </div>

        </div>

    `);
}