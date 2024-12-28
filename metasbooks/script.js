jQuery(document).ready(function ($) {
    
    $('#submit_apikey').on('click', function () {
        var apikey = $('#apikey').val();
        $.ajax({
            type: "post",
            url: metasbooks_custom_ajax.ajax_url,
            data: {
                case: 'update_apikey',
                apikey: apikey,
                action: 'metasbooks',
                _ajax_nonce: metasbooks_custom_ajax.nonce
            },
            dataType: "text",
            success: function (r) {
                switch (r) {
                    case 'success':
                        $('#apikey_resp').html('Clé d\'API valide &check;');
                        break;
                    case 'inv_key':
                        $('#apikey_resp').html('Clé d\'API invalide &#10060;');
                        break;
                    case 'not_activ':
                        $('#apikey_resp').html('Votre compte n\'a pas été correctement activé &#10060;');
                        break;
                    default:
                        $('#apikey_resp').html('Une erreur s\'est produite: support@metasbooks.fr &#10060;');
                }
                setTimeout(() => {
                    document.location.reload(true);
                }, 1000);
            }   
        });
    });

    $(document).ready(function () {
        $('#xml_file').change(function () {
            var file = this.files[0];

            if (file) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    var xmlContent = e.target.result;
                    var parser = new DOMParser();
                    var xmlDoc = parser.parseFromString(xmlContent, 'text/xml');

                    var isValid = false;

                    var catalog = xmlDoc.getElementsByTagName('catalog');
                    for (var i = 0; i < catalog.length; i++)
                    {
                        var cat = catalog[i];
                        var records = cat.getElementsByTagName('record');

                        for (var j = 0; j < records.length; j++) {
                            var record = records[j];
                            var ean = record.getElementsByTagName('ean')[0];
                            var stock = record.getElementsByTagName('stock')[0];

                            if (ean && stock) {
                                isValid = true;
                                break;
                            }
                        }

                    }
                    

                    if (isValid) {
                        $('#xml_valid').html('Votre fichier est correctement formaté &check;');
                        $('#syncronisation_box').css('display', 'block');
                    } else {
                        $('#xml_valid').html('Votre fichier semble être mal formaté &#10060;');
                    }
                };

                reader.readAsText(file);
            } else {
                $('#xml_valid').text('Aucun fichier séléctionné.');
            }
        });
    });

    $("#lauch_sync").on('click', function () {

        $.ajax({
            type: "post",
            url: metasbooks_custom_ajax.ajax_url,
            data: {
                case: 'reset_stocks',
                action: 'metasbooks',
                _ajax_nonce: metasbooks_custom_ajax.nonce
            },
            dataType: "dataType"
        });

        $('#progression').css('display', 'block');
        $('.metasbooks_xml_prev').css('display', 'none')

        var selectedFile = $('#xml_file').prop('files')[0];

        if (selectedFile) {
            var reader = new FileReader();

            reader.onload = function (e) {
                var xmlContent = e.target.result;
                var xmlDoc = $.parseXML(xmlContent);
                var $xml = $(xmlDoc);
                var records = $xml.find('record');
                var totalRequests = records.length;
                var completedRequests = 0;

                function initializeProgressBar() {
                    $('#progressBar').width('0%');
                    $('.progressBar_container').css('display', 'block');
                }

                function updateProgressBar(current, total) {
                    var progressPercentage = (current / total) * 100;
                    $('#progressBar').width(progressPercentage + '%');
                }

                initializeProgressBar();

                function processAjaxRequests(index) {
                    var ajaxQueue = [];

                    

                    for (var i = index; i < index + 30 && i < records.length; i++) {
                        var record = $(records[i]);
                        var ean = record.find('ean').text();
                        var stock = record.find('stock').text();

                        ajaxQueue.push(
                            $.ajax({
                                type: "post",
                                url: metasbooks_custom_ajax.ajax_url,
                                data: {
                                    case: 'update_create',
                                    ean: ean,
                                    stock: stock,
                                    action: 'metasbooks',
                                    _ajax_nonce: metasbooks_custom_ajax.nonce
                                },
                                dataType: "text"
                            })
                        );
                    }

                    $.when.apply($, ajaxQueue).done(function () {
                        

                        var responses = Array.from(arguments);
                        var is_ean = /^\d{13}$/;
                        
                        responses.forEach(function (response) {

                            var callback = response[0];
                            
                            if (callback == 'created') {
                                var createdCount = parseInt($('#created').text()) + 1;
                                $('#created').text(createdCount);
                            }
                            if (callback == 'updated') {
                                var updatedCount = parseInt($('#updated').text()) + 1;
                                $('#updated').text(updatedCount);
                            }
                            if (is_ean.test(callback))
                            {
                                $('#not_found_eans_container').css('display', 'block');
                                var eans_not_found_content = $('#not_found_eans').html();
                                var new_eans_not_found_content = eans_not_found_content + callback + ', ';
                                $('#not_found_eans').html(new_eans_not_found_content);
                            }
                        });

                        completedRequests += responses.length; // Mettre à jour le nombre de requêtes terminées
                        console.log(completedRequests);
                        updateProgressBar(completedRequests, totalRequests);

                        // Continue processing the next batch of requests
                        if (index + 30 < records.length) {
                            processAjaxRequests(index + 30);
                        }
                        else {
                            updateProgressBar(totalRequests, totalRequests);
                            $('#done').html('<br><br> Synchronisation terminée !').css('display', 'block');
                        }
                        
                    });
                    
                }

                // Start processing the AJAX requests
                processAjaxRequests(0);
            };

            reader.readAsText(selectedFile);
            
        }

    });

});