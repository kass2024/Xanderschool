var base_url = null;
$(function () {
    var active_btn = null;
    window.base_url = $("body").data("url");
    $(".validate").parsley();
    $(".select2").select2();
    // $(".select3").select2(
    //     {
    //         $('.js-data-example-ajax').select2({
    //         ajax: {
    //         url: 'https:base_url("get_student/"+data1+"/"+data2'),
    //         dataType: 'json'
    //             }
    //         });
    //     });
    $(document).on("click", "form [type='submit']", function () {
        active_btn = $(this);
    });

    //function for address selection
    $(".address_select").on("change",function () {
        var target = $(this).data("target");
        var current = $(this).prop("name");
        var value = $(this).val();
        $.get(base_url+"get_address/"+target,"key="+current+"&val="+value,function (data) {
            $("[name='"+target+"']").html(data);
        })
    });

    $(".autoSubmit").on("submit", function (e) {
        e.preventDefault();
        var form = $(this);
        // var btn = $(this).find("[type='submit']");
        var btn = active_btn;
        var btn_txt = btn.text();
        btn.text("Please wait...").prop("disabled", true);
        $.post(form.prop("action"), $(this).serialize(), function (data) {
            btn.text(btn_txt).prop("disabled", false);
            if (data.hasOwnProperty("error")) {
                toastada.error(data.error);
                // alert(data.error);
            } else if (data.hasOwnProperty("success")) {
                if (btn.data("target")) {
                    toastada.success(data.success);
                    var target=btn.data("target");
                    if (target.startsWith("#")){
                        //try close modal
                        $(target).modal('hide');
                        return;
                    }
                    if (target == "reload"){
                        setTimeout(function () {
                            window.location.reload();
                        }, 1500);
                        return;
                    }
                    if (target == "open" && data.hasOwnProperty("url")){
                        $("#anchorID").prop("href",data.url);
                        setTimeout(function () {
                            $("#anchorID")[0].click();
                            window.location.reload();
                        }, 1500);
                        return;
                    }
                    setTimeout(function () {
                        window.location.href = btn.data("target");
                    }, 1500);
                } else {
                    toastada.success(data.success);
                    form.trigger("reset");
                }
            } else {
                toastada.error("Fatal error occurred, if the problem persist please contact system admin");
            }
        }).fail(function () {
            //unknown error
            btn.text(btn_txt).prop("disabled", false);
            toastada.error("System server error, please try again later");
        });
    });
    $(document).on("click", "[data-toggle='refresh']", function () {
        var target = $(this).data("target");
        var href = $(this).data("href");
        var trigger = $(this).find("i");
        trigger.removeClass("fa-sync").addClass("fa-spinner").addClass("animated");
        $.get(href, "", function (data) {
            trigger.removeClass("animated").removeClass("fa-spinner").addClass("fa-sync");
            $("#" + target).html(data);
        }).fail(function () {
            //unknown error
            toastada.error("System server error, please try again later");
        });;
    });
    $(document).on("click", "[data-toggle='update']", function () {
        if (!confirm("Do you want to change status?"))
            return;
        var target = $(this).data("target");
        var href = $(this).data("href");
        var record_id = "";
        if($(this).data("target-record")!= undefined){
            record_id = "&record_id="+$(this).data("target-record");
        }
        $.post(base_url + href, "data=" + target+record_id, function (data) {
            if (data.hasOwnProperty("error")) {
                toastada.error(data.error);
                // alert(data.error);
            } else if (data.hasOwnProperty("success")) {
                toastada.success(data.success);
                setTimeout(function () {
                    window.location.reload();
                }, 1500);
            } else {
                toastada.error("Fatal error occurred, if the problem persist please contact system admin");
            }
        }).fail(function () {
            //unknown error
            toastada.error("System server error, please try again later");
        });;
    });
    $(document).on("click", "[data-toggle='delete']", function () {
        var title = $(this).data("title") == undefined?"item":$(this).data("title");
        if (!confirm("Do you want to delete "+title+"?"))
            return;
        var target = $(this).data("target");
        var href = $(this).data("href");
        $.post(base_url + href, "data=" + target, function (data) {
            if (data.hasOwnProperty("error")) {
                toastada.error(data.error);
                // alert(data.error);
            } else if (data.hasOwnProperty("success")) {
                toastada.success(data.success);
                setTimeout(function () {
                    window.location.reload();
                }, 1500);
            } else {
                toastada.error("Fatal error occurred, if the problem persist please contact system admin");
            }
        }).fail(function () {
            //unknown error
            toastada.error("System server error, please try again later");
        });
    });

    //date mask
    $(".date_mask").inputmask("d/m/y", {
        "placeholder": "__/__/____"
    });

    $(document).ajaxStart(function () {
        $(".slider").show();
    }).ajaxComplete(function () {
        $(".slider").hide();
    });
});