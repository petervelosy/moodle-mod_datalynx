// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 
/**
 * @package mod
 * @subpackage datalynx
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Datalynx views loader
 */
M.mod_datalynx_load_views = {};

M.mod_datalynx_load_views.init = function(Y, options) {
    YUI().use('node-base', 'event-base', 'io-base', function(Y) {
        // get field name from options
        var dffield = options.dffield;
        var viewfield = options.viewfield;
        var filterfield = options.filterfield;
        var textfieldfield = options.textfieldfield;
        var actionurl = options.acturl;

        Y.on('change', function(e) {

            // get view select
            var view = Y.Node.one('#id_' + viewfield);

            // get filter select
            var filter = Y.Node.one('#id_' + filterfield);

            // get textfield select
            var textfield = Y.Node.one('#id_' + textfieldfield);

            // get the datalynx id
            var dfid = this.get('options').item(this.get('selectedIndex')).get('value');

            // remove view, filter, textfield options (but the first choose) from view select
            var viewchoose = view.get('options').item(0);
            view.setContent(viewchoose);
            view.set('selectedIndex', 0);           
            var filterchoose = filter.get('options').item(0);
            filter.setContent(filterchoose);
            filter.set('selectedIndex', 0);
            var textfieldchoose = textfield.get('options').item(0);
            textfield.setContent(textfieldchoose);
            textfield.set('selectedIndex', 0);

            // load views, filters, textfields from datalynx
            if (dfid != 0) {

                Y.io(actionurl, {
                    method: 'POST',
                    data: 'dfid='+dfid,
                    on: {
                        success: function (id, o) {
                            if (o.responseText != '') {
                                console.log(o.responseText);
                                var respoptions = o.responseText.split('#');

                                // add view options
                                var viewoptions = respoptions[0].split(',');
 		                        for (var i=0;i<viewoptions.length;++i) {
		                            var arr = viewoptions[i].trim().split(' ');
                                    var qid = arr.shift();
                                    var qname = arr.join(' ');
		                            view.append(Y.Node.create('<option value="'+qid+'">'+qname+'</option>'));
                                }
                                
                                // add filter options
                                var filteroptions = respoptions[1].split(',');
 		                        for (var i=0;i<filteroptions.length;++i) {
		                            var arr = filteroptions[i].trim().split(' ');
                                    var qid = arr.shift();
                                    var qname = arr.join(' ');
		                            filter.append(Y.Node.create('<option value="'+qid+'">'+qname+'</option>'));
	                            }

                                // add textfield options
                                var textfieldoptions = respoptions[2].split(',');
                                for (var i=0;i<textfieldoptions.length;++i) {
                                    var arr = textfieldoptions[i].trim().split(' ');
                                    var qid = arr.shift();
                                    var qname = arr.join(' ');
                                    textfield.append(Y.Node.create('<option value="'+qid+'">'+qname+'</option>'));
                                }
                            }
                        },
                        failure: function (id, o) {
		                    console.log("Error while loading views, filters and textfields.")
                        }
                    }
                });

            }
        }, '#id_'+ dffield);
    });        
};
