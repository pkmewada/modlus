$(function () {
    
    window.addEventListener("error", function (e) {
        console.log("JS ERROR:", e.message, e.filename, e.lineno);
    });


    var leadCategories = [];
    var leadPlans = [];

    loadLeadMasterData();

    var statusColumnIndex = 5;
    var sourceColumnIndex = 4;
    var addLeadApiUrl = API_BASE + "/leads/addLead.php";
    var updateLeadApiUrl = API_BASE + "/leads/updateLead.php";
    var updateLeadStatusApiUrl = API_BASE + "/leads/updateLeadStatus.php";
    var deleteLeadApiUrl = API_BASE + "/leads/deleteLead.php";
    var saveLeadRemarkApiUrl = API_BASE + "/leads/saveLeadRemark.php";
    var getLeadRemarksApiUrl = API_BASE + "/leads/getLeadRemarks.php";
    var getScheduledCallsApiUrl = API_BASE + "/leads/getScheduledCalls.php";
    var uploadLeadDocumentApiUrl = API_BASE + "/leads/uploadLeadDocument.php";
    var getLeadDocumentsApiUrl = API_BASE + "/leads/getLeadDocuments.php";
    var importLeadsApiUrl = API_BASE + "/leads/importLeads.php";

    var table = $("#leads-datatable").DataTable(
        window.ModlusUI.withDataTableDefaults({
            data: leadsData,
            deferRender: true,
            order: [[7, 'desc']],
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                { data: 'fullName', render: (data, type, row) => 
                    row.fullName + 
                    '<small class="d-block text-muted">' + row.email + '</small>' +
                    '<small class="d-block text-muted">' + row.phone + '</small>'
                },
                { data: 'employeeName', defaultContent: 'Admin' },
                { data: null, render: (data, type, row) => 
                    (row.categoryName ?? '-') + 
                    '<small class="d-block text-muted">' + (row.planName ?? '-') + '</small>'
                },
                { data: 'source' },
                { data: 'status', render: (data, type, row) => 
                    getStatusDropdownHtml(row.id, row.status)
                },
                { data: 'orgName', defaultContent: '-', render: data => 
                    data ? data.replace(/(.{20})/g, '$1<br>') : '-'
                },
                { data: 'createdAt', render: data => 
                    new Date(data).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12:true })
                },
                { data: null, orderable: false, searchable: false, render: (data, type, row) => 
                    '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-warning-light remark-btn" data-id="'+row.id+'" data-name="'+row.fullName+'"><i class="ri-chat-3-line"></i></a> ' +
                    '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-info-light edit-lead-btn" data-id="'+row.id+'" data-fullname="'+row.fullName+'" data-email="'+row.email+'" data-phone="'+row.phone+'" data-source="'+row.source+'" data-orgname="'+row.orgName+'" data-categoryid="'+row.categoryId+'" data-planid="'+row.planId+'"><i class="ri-edit-line"></i></a> ' +
                    '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-secondary-light document-btn" data-id="'+row.id+'" data-name="'+row.fullName+'"><i class="ri-file-pdf-line"></i></a> ' +
                    '<a href="https://wa.me/91'+row.phone.replace(/\D/g,'')+'?text=Hello%20'+encodeURIComponent(row.fullName)+'" target="_blank" class="btn btn-icon btn-sm btn-success-light"><i class="ri-whatsapp-line"></i></a> ' +
                    '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-danger-light delete-lead-btn" data-id="'+row.id+'"><i class="ri-delete-bin-line"></i></a>'
                }
            ],
            dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
            buttons: [
                { extend: "csvHtml5", className: "d-none buttons-csv", exportOptions: { columns: [0,1,2,3,4,5,6,7,8] } },
                { extend: "pdfHtml5", className: "d-none buttons-pdf", exportOptions: { columns: [0,1,2,3,4,5,6,7,8] } }
            ],
            pageLength: 20
        })
    );

    var sourceFilter = $("#sourceFilter");
    var statusFilter = $("#statusFilter");

    // Populate filters immediately using the data already in the table
    populateFilter(sourceFilter, sourceColumnIndex);
    populateFilter(statusFilter, statusColumnIndex);

    function populateFilter(selectEl, columnIndex) {
        selectEl.find('option:not(:first)').remove(); // Clear existing options (keep the first "all" option)
        var uniqueValues = table.column(columnIndex).data().unique().sort().toArray();

        $.each(uniqueValues, function (_, value) {
            var cleanedValue = $("<div>").html(value).text().trim();
            if (cleanedValue !== "") {
                selectEl.append(
                    $("<option>", {
                        value: cleanedValue,
                        text: cleanedValue,
                    })
                );
            }
        });
    }

    function getStatusDropdownHtml(id, status) {
        var statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        return (
            '<div class="btn-group" data-id="' + id + '">' +
            '<button type="button" class="btn btn-sm dropdown-toggle lead-status-btn lead-status-' + status + '" data-bs-toggle="dropdown" aria-expanded="false" data-status="' + status + '">' +
            statusLabel +
            "</button>" +
            '<ul class="dropdown-menu">' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="open">Open</a></li>' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="interested">Interested</a></li>' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="converted">Converted</a></li>' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="not_interested">Not Interested</a></li>' +
            "</ul>" +
            "</div>"
        );
    }

    function addOptionIfMissing(selectEl, value, text) {
        if (!value) return;
        if (selectEl.find('option[value="' + value.replace(/"/g, '\\"') + '"]').length === 0) {
            selectEl.append(
                $("<option>", {
                    value: value,
                    text: text || value,
                })
            );
        }
    }

    sourceFilter.on("change", function () {
        var value = $(this).val();
        table
            .column(sourceColumnIndex)
            .search(value ? "^" + $.fn.dataTable.util.escapeRegex(value) + "$" : "", true, false)
            .draw();
    });

    statusFilter.on("change", function () {
        table.draw();
    });

    // Custom status filter (works with rendered buttons)
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== "leads-datatable") return true;
        var selectedStatus = statusFilter.val();
        if (!selectedStatus) return true;
        var rowNode = table.row(dataIndex).node();
        var rowStatus = $(rowNode).find(".lead-status-btn").data("status");
        return rowStatus === selectedStatus;
    });

    $(document).on("click", ".change-status", function (event) {
        event.preventDefault();
        var status = $(this).data("status");
        var parentGroup = $(this).closest(".btn-group");
        var leadId = parentGroup.data("id");

        if (!leadId || !status) return;

        if (status === "converted" || status === "not_interested") {
            openLeadStatusModal(leadId, status);
            return;
        }

        $.ajax({
            url: updateLeadStatusApiUrl,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({ id: leadId, status: status }),
        })
            .done(function (res) {
                if (res && res.success) {
                    var button = parentGroup.find(".lead-status-btn");
                    var label = status.charAt(0).toUpperCase() + status.slice(1);
                    button
                        .text(label)
                        .removeClass("lead-status-open lead-status-interested lead-status-converted lead-status-not_interested")
                        .addClass("lead-status-btn lead-status-" + status)
                        .attr("data-status", status);
                    window.showToast("success", "Status updated");
                }
            })
            .fail(function (xhr) {
                console.log("FAILED", xhr.status, xhr.responseText);
            });
    });

    $("#leads-datatable_wrapper .dataTables_length select").addClass("form-select form-select-sm");

    $(".export-btn").on("click", function () {
        var type = $(this).data("type");
        if (type === "csv") table.button(".buttons-csv").trigger();
        if (type === "pdf") table.button(".buttons-pdf").trigger();
    });

    $("#tableSearch").on("keyup", function () {
        table.search(this.value).draw();
    });

    table
        .on("order.dt search.dt draw.dt", function () {
            var info = table.page.info();
            table.column(0, { search: "applied", order: "applied", page: "current" })
                .nodes()
                .each(function (cell, index) {
                    cell.innerHTML = info.start + index + 1;
                });
        })
        .draw();

    // ---------- Add / Edit Lead Form ----------
    var addLeadForm = $("#addLeadForm");
    var submitButton = $("#addLeadSubmitBtn");
    var submitSpinner = $("#addLeadSubmitSpinner");
    var submitText = $("#addLeadSubmitText");

    addLeadForm.on("submit", function (event) {
        event.preventDefault();
        event.stopPropagation();
        var formEl = this;
        formEl.classList.add("was-validated");

        if (!formEl.checkValidity()) {
            if (typeof window.showToast === "function") window.showToast("warning", "Please fill all required fields");
            return;
        }

        var isEdit = $("#leadId").val() !== "";
        var apiUrl = isEdit ? updateLeadApiUrl : addLeadApiUrl;
        var successMessage = isEdit ? "Lead updated successfully" : "Lead added successfully";
        var failMessage = isEdit ? "Failed to update lead" : "Failed to add lead";

        var payload = {
            fullName: $.trim($("#modal-fullName").val()),
            email: $.trim($("#modal-email").val()),
            phone: $.trim($("#modal-phone").val()),
            source: $.trim($("#modal-source").val()),
            orgName: $.trim($("#modal-orgName").val()),
            categoryId: parseInt($("#modal-categoryId").val() || 0),
            planId: parseInt($("#modal-planId").val() || 0),
        };
        if (isEdit) payload.id = $("#leadId").val();

        submitButton.prop("disabled", true);
        submitSpinner.removeClass("d-none");
        submitText.text(isEdit ? "Updating..." : "Saving...");

        $.ajax({
            url: apiUrl,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify(payload),
        })
            .done(function (response) {
                if (response && response.success) {
                    var lead = response.data || {};
                    var leadId = lead.id || payload.id || 0;
                    var statusValue = lead.status || "open";
                    var createdDate = lead.createdDate || "";

                    if (isEdit) {
                        var row = table.row(function (idx, data, node) {
                            return $(node).find(".edit-lead-btn").data("id") == leadId;
                        });
                        if (row.any()) {
                            var currentData = row.data();
                            var updatedRowData = {
                                ...currentData,
                                id: leadId,
                                fullName: lead.fullName || payload.fullName,
                                email: lead.email || payload.email,
                                phone: lead.phone || payload.phone,
                                source: lead.source || payload.source,
                                orgName: lead.orgName || payload.orgName,
                                categoryId: lead.categoryId || payload.categoryId,
                                planId: lead.planId || payload.planId,
                                status: statusValue,
                                employeeName: lead.employeeName || currentData.employeeName || '',
                                categoryName: lead.categoryName || currentData.categoryName || '',
                                planName: lead.planName || currentData.planName || ''
                            };
                            row.data(updatedRowData).draw(false);
                        }
                    } else {
                        var newLeadObject = {
                            id: leadId,
                            fullName: lead.fullName || payload.fullName,
                            email: lead.email || payload.email,
                            phone: lead.phone || payload.phone,
                            source: lead.source || payload.source,
                            orgName: lead.orgName || payload.orgName || '-',
                            categoryId: lead.categoryId || payload.categoryId || 0,
                            planId: lead.planId || payload.planId || 0,
                            status: statusValue,
                            createdAt: createdDate,
                            employeeName: lead.employeeName || '',
                            categoryName: lead.categoryName || '',
                            planName: lead.planName || ''
                        };
                        table.row.add(newLeadObject).draw(false);
                    }

                    addOptionIfMissing(sourceFilter, lead.source || payload.source, lead.orgName || payload.orgName);
                    addOptionIfMissing(statusFilter, statusValue, statusValue.charAt(0).toUpperCase() + statusValue.slice(1));

                    formEl.reset();
                    formEl.classList.remove("was-validated");
                    $("#addLeadModal").modal("hide");
                    if (typeof window.showToast === "function") window.showToast("success", response.message || successMessage);
                } else {
                    if (typeof window.showToast === "function") window.showToast("danger", response && response.message ? response.message : failMessage);
                }
            })
            .fail(function (xhr) {
                var message = failMessage;
                if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                if (typeof window.showToast === "function") window.showToast("danger", message);
            })
            .always(function () {
                submitButton.prop("disabled", false);
                submitSpinner.addClass("d-none");
                submitText.text(isEdit ? "Update Lead" : "Save Lead");
            });
    });

    // Edit Lead button
    $("#leads-datatable").on("click", ".edit-lead-btn", function () {
        var btn = $(this);
        $("#leadId").val(btn.data("id"));
        $("#modal-fullName").val(btn.data("fullname"));
        $("#modal-email").val(btn.data("email"));
        $("#modal-phone").val(btn.data("phone"));
        $("#modal-source").val(btn.data("source"));
        $("#modal-orgName").val(btn.data("orgname"));
        $("#modal-categoryId").val(btn.data("categoryid")).trigger("change");
        setTimeout(function () {
            $("#modal-planId").val(btn.data("planid"));
        }, 100);
        $("#addLeadModalLabel").text("Edit Lead");
        $("#addLeadSubmitText").text("Update Lead");
        $("#addLeadModal").modal("show");
    });

    // Delete Lead
    $("#leads-datatable").on("click", ".delete-lead-btn", function () {
        var id = $(this).data("id");
        var modal = $("#deleteConfirmModal");
        var effect = modal.data("bs-effect");
        if (effect) modal.addClass(effect);
        modal.data("deleteId", id).modal("show");
    });

    $("#deleteConfirmModal").on("hidden.bs.modal", function () {
        var modal = $(this);
        var effect = modal.data("bs-effect");
        if (effect) modal.removeClass(effect);
    });

    $("#addLeadModal").on("show.bs.modal", function () {
        var modal = $(this);
        var effect = modal.data("bs-effect");
        if (effect) modal.addClass(effect);
        if ($("#leadId").val() === "") {
            $("#addLeadForm")[0].reset();
            $("#addLeadForm")[0].classList.remove("was-validated");
            $("#addLeadModalLabel").text("Add Lead");
            $("#addLeadSubmitText").text("Save Lead");
        }
    });

    $("#addLeadModal").on("hidden.bs.modal", function () {
        var modal = $(this);
        var effect = modal.data("bs-effect");
        if (effect) modal.removeClass(effect);
        $("#leadId").val("");
    });

    $("#confirmDeleteBtn").on("click", function () {
        var id = $("#deleteConfirmModal").data("deleteId");
        $("#deleteConfirmModal").modal("hide");
        $.ajax({
            url: deleteLeadApiUrl,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({ id: id }),
        })
            .done(function (response) {
                if (response && response.success) {
                    var tr = $('.delete-lead-btn[data-id="' + id + '"]').closest("tr");
                    table.row(tr).remove().draw(false);
                    if (typeof window.showToast === "function") window.showToast("success", response.message || "Lead deleted successfully");
                } else {
                    if (typeof window.showToast === "function") window.showToast("danger", response && response.message ? response.message : "Failed to delete lead");
                }
            })
            .fail(function (xhr) {
                var message = "Failed to delete lead";
                if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                if (typeof window.showToast === "function") window.showToast("danger", message);
            });
    });

    // Remarks
    $("#leads-datatable").on("click", ".remark-btn", function () {
        var leadId = $(this).data("id");
        var leadName = $(this).data("name");
        $("#remarkLeadId").val(leadId);
        $("#remarkLeadName").text(leadName);
        $("#leadRemark").val("");
        loadLeadRemarks(leadId);
        $("#leadRemarkModal").modal("show");
    });

    function loadLeadRemarks(leadId) {
        $.getJSON(getLeadRemarksApiUrl, { leadId: leadId }, function (response) {
            let html = "";
            if (response.success && response.data.length) {
                response.data.forEach(function (item) {
                    html += `
                        <div class="border rounded p-3 mb-2">
                            <div class="small text-muted">${item.createdAt}</div>
                            <div class="mt-2">${item.remark}</div>
                            ${item.followUpDateTime ? `<div class="text-primary mt-2">Follow Up : ${item.followUpDateTime}</div>` : ""}
                            <div class="small text-muted mt-2">By ${item.employeeName}</div>
                        </div>`;
                });
            }
            $("#remarkTimeline").html(html);
        });
    }

    $(document).on("click", "#saveRemarkBtn", function () {
        $.ajax({
            url: saveLeadRemarkApiUrl,
            type: "POST",
            dataType: "json",
            data: {
                leadId: $("#remarkLeadId").val(),
                remark: $("#leadRemark").val(),
                followUpDateTime: $("#followUpDateTime").val(),
            },
            success: function (response) {
                if (response.success) {
                    $("#leadRemark").val("");
                    loadLeadRemarks($("#remarkLeadId").val());
                    window.showToast("success", "Remark saved");
                }
            },
        });
    });

    // Master data
    function loadLeadMasterData() {
        $.getJSON(API_BASE + "/leads/getLeadMasterData.php", function (response) {
            if (!response.success) return;
            leadCategories = response.data.categories || [];
            leadPlans = response.data.plans || [];
            populateCategoryDropdown();
        });
    }

    function populateCategoryDropdown() {
        let html = '<option value="">Select Category</option>';
        leadCategories.forEach(function (cat) {
            html += `<option value="${cat.id}">${cat.categoryName}</option>`;
        });
        $("#modal-categoryId").html(html);
    }

    $(document).on("change", "#modal-categoryId", function () {
        let categoryId = $(this).val();
        let html = '<option value="">Select Plan</option>';
        leadPlans.forEach(function (plan) {
            if (String(plan.categoryId) === String(categoryId)) {
                html += `<option value="${plan.id}">${plan.planName}</option>`;
            }
        });
        $("#modal-planId").html(html);
    });

    // Scheduled Calls
    $(document).on("click", "#scheduledCallsBtn", function () {
        var today = new Date().toISOString().split("T")[0];
        $("#scheduledCallsDate").val(today);
        loadScheduledCalls(today);
        $("#scheduledCallsModal").modal("show");
    });

    $(document).on("change", "#scheduledCallsDate", function () {
        loadScheduledCalls($(this).val());
    });

    function loadScheduledCalls(date) {
        $.ajax({
            url: getScheduledCallsApiUrl,
            type: "GET",
            dataType: "json",
            data: { date: date },
            success: function (response) {
                var html = "";
                if (!response.success || !response.data.length) {
                    html = `<tr><td colspan="7" class="text-center text-muted py-4">No follow-ups scheduled for this date.</td></tr>`;
                    $("#scheduledCallsTableBody").html(html);
                    return;
                }
                response.data.forEach(function (item, index) {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.followUpTime}</td>
                            <td>${item.leadName}</td>
                            <td>${item.phone}</td>
                            <td>${item.employeeName}</td>
                            <td>
                                <select class="form-select form-select-sm followup-status" style="min-width:110px;" data-id="${item.leadId}">
                                    <option value="open" ${item.status.toLowerCase() === "open" ? "selected" : ""}>Open</option>
                                    <option value="close" ${item.status.toLowerCase() === "close" ? "selected" : ""}>Close</option>
                                </select>
                            </td>
                            <td>${item.remark}</td>
                        </tr>`;
                });
                $("#scheduledCallsTableBody").html(html);
            },
        });
    }

    // Documents
    $(document).on("click", ".document-btn", function () {
        var leadId = $(this).data("id");
        var leadName = $(this).data("name");
        $("#documentLeadId").val(leadId);
        $("#documentLeadName").text(leadName);
        $("#leadDocumentFile").val("");
        loadLeadDocuments(leadId);
        $("#leadDocumentsModal").modal("show");
    });

    function loadLeadDocuments(leadId) {
        $.ajax({
            url: getLeadDocumentsApiUrl,
            type: "GET",
            dataType: "json",
            data: { leadId: leadId },
            success: function (response) {
                var html = "";
                if (!response.success || !response.data.length) {
                    html = `<div class="text-center text-muted py-4">No documents uploaded.</div>`;
                    $("#leadDocumentsContainer").html(html);
                    return;
                }
                response.data.forEach(function (doc) {
                    html += `
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold"><i class="ri-file-pdf-line text-danger me-1"></i>${doc.fileName}</div>
                                    <div class="small text-muted mt-1">Uploaded By : ${doc.employeeName}</div>
                                    <div class="small text-muted">${doc.uploadedAt}</div>
                                </div>
                                <div>
                                    <a href="${doc.viewUrl}" target="_blank" class="btn btn-sm btn-outline-primary me-1">View</a>
                                    <a href="${doc.downloadUrl}" target="_blank" download class="btn btn-sm btn-outline-success">Download</a>
                                </div>
                            </div>
                        </div>`;
                });
                $("#leadDocumentsContainer").html(html);
            },
        });
    }

    $(document).on("click", "#uploadLeadDocumentBtn", function () {
        var leadId = $("#documentLeadId").val();
        var file = $("#leadDocumentFile")[0].files[0];
        if (!file) {
            showToast("warning", "Please select a PDF.");
            return;
        }
        var formData = new FormData();
        formData.append("leadId", leadId);
        formData.append("document", file);
        $.ajax({
            url: uploadLeadDocumentApiUrl,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function (response) {
                if (!response.success) {
                    showToast("error", response.message);
                    return;
                }
                showToast("success", response.message);
                $("#leadDocumentFile").val("");
                loadLeadDocuments(leadId);
            },
            error: function () {
                showToast("error", "Upload failed.");
            },
        });
    });

    // Follow-up close
    $(document).on("change", ".followup-status", function () {
        let status = $(this).val();
        let leadId = $(this).data("id");
        if (status === "close") {
            $("#followupLeadId").val(leadId);
            $("#followupCloseRemark").val("");
            $("#followupRemarkModal").modal("show");
        }
    });

    $(document).on("click", "#saveFollowupRemarkBtn", function () {
        let leadId = $("#followupLeadId").val();
        let remark = $("#followupCloseRemark").val().trim();
        if (remark === "") {
            showToast("warning", "Please enter remark");
            return;
        }
        $.ajax({
            url: API_BASE + "/leads/closeFollowup.php",
            type: "POST",
            dataType: "json",
            data: { leadId: leadId, remark: remark },
            success: function (response) {
                if (response.success) {
                    $("#followupRemarkModal").modal("hide");
                    showToast("success", "Follow up closed");
                    loadScheduledCalls($("#scheduledCallsDate").val());
                }
            }
        });
    });

    // Status change modal
    function openLeadStatusModal(leadId, status) {
        $("#statusLeadId").val(leadId);
        $("#selectedLeadStatus").val(status);
        $("#selectedStatusText").text(status.replace("_", " ").replace(/\b\w/g, c => c.toUpperCase()));
        $("#statusRemark").val("");
        $("#finalPrice").val("");
        $("#nextPriceIncrementDate").val("");
        $("#quotationDocument").val("");
        if (status === "converted") {
            $("#convertedFields").show();
        } else {
            $("#convertedFields").hide();
        }
        $("#leadStatusModal").modal("show");
    }

    $(document).on("click", "#saveLeadStatusBtn", function () {
        var leadId = $("#statusLeadId").val();
        var status = $("#selectedLeadStatus").val();
        var remark = $("#statusRemark").val().trim();
        if (remark === "") {
            showToast("warning", "Please enter remark.");
            return;
        }
        if (status === "converted") {
            saveConvertedLead(leadId, status, remark);
        } else {
            saveNotInterestedLead(leadId, status, remark);
        }
    });

    function saveNotInterestedLead(leadId, status, remark) {
        $.ajax({
            url: API_BASE + "/leads/saveLeadStatusRemark.php",
            type: "POST",
            dataType: "json",
            contentType: "application/json",
            data: JSON.stringify({ leadId: leadId, status: status, remark: remark }),
        })
            .done(function (response) {
                if (!response.success) {
                    showToast("error", response.message);
                    return;
                }
                updateLeadStatus(leadId, status);
            })
            .fail(function () {
                showToast("error", "Failed to save remark.");
            });
    }

    function saveConvertedLead(leadId, status, remark) {
        var finalPrice = $("#finalPrice").val();
        if (finalPrice === "") {
            showToast("warning", "Please enter final price.");
            return;
        }
        var formData = new FormData();
        formData.append("leadId", leadId);
        formData.append("statusRemark", remark);
        formData.append("finalPrice", finalPrice);
        formData.append("nextPriceIncrementDate", $("#nextPriceIncrementDate").val());
        if ($("#quotationDocument")[0].files.length) {
            formData.append("quotationDocument", $("#quotationDocument")[0].files[0]);
        }
        $.ajax({
            url: API_BASE + "/leads/saveLeadConversion.php",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
        })
            .done(function (response) {
                if (!response.success) {
                    showToast("error", response.message);
                    return;
                }
                updateLeadStatus(leadId, status);
            })
            .fail(function () {
                showToast("error", "Failed to save conversion.");
            });
    }

    function updateLeadStatus(leadId, status) {
        $.ajax({
            url: updateLeadStatusApiUrl,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({ id: leadId, status: status }),
        })
            .done(function (response) {
                if (!response.success) {
                    showToast("error", response.message);
                    return;
                }
                $("#leadStatusModal").modal("hide");
                showToast("success", response.message || "Lead status updated successfully.");
                setTimeout(function () {
                    location.reload();
                }, 1200);
            })
            .fail(function () {
                showToast("error", "Status update failed.");
            });
    }

    // Import Leads
    $(document).on("submit", "#importLeadForm", function (event) {
        event.preventDefault();
        event.stopPropagation();
        console.log("Import form submitted");
        var employeeId = $("#importEmployeeId").val();
        var fileInput = $("#leadCsvFile")[0];
        var file = fileInput && fileInput.files.length ? fileInput.files[0] : null;
        if (!employeeId) {
            showToast("warning", "Please select employee.");
            return;
        }
        if (!file) {
            showToast("warning", "Please upload CSV file.");
            return;
        }
        var formData = new FormData();
        formData.append("employeeId", employeeId);
        formData.append("leadCsvFile", file);
        $("#importLeadSubmitBtn").prop("disabled", true);
        $("#importLeadSpinner").removeClass("d-none");
        $.ajax({
            url: importLeadsApiUrl,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            timeout: 60000
        })
            .done(function (response) {
                if (!response.success) {
                    showToast("error", response.message || "Import failed.");
                    return;
                }
                showToast("success", response.message || "Leads imported successfully.");
                $("#importLeadModal").modal("hide");
                $("#importLeadForm")[0].reset();
                location.reload();
            })
            .fail(function (xhr, status, error) {
                var message = "Import failed.";
                if (status === "timeout") message = "Import request timed out.";
                if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                showToast("error", message);
            })
            .always(function () {
                $("#importLeadSubmitBtn").prop("disabled", false);
                $("#importLeadSpinner").addClass("d-none");
            });
    });

    $("#importLeadModal").on("show.bs.modal hidden.bs.modal", function () {
        $("#importLeadSubmitBtn").prop("disabled", false);
        $("#importLeadSpinner").addClass("d-none");
    });
    
});