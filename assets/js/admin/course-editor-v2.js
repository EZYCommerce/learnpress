;

/**
 * Store
 */
(function (exports, Vue, Vuex, data) {
    var state = data;

    state.status = 'success';
    state.countCurrentRequest = 0;

    var getters = {
        action: function (state) {
            return state.action;
        },
        sections: function (state) {
            return state.sections || [];
        },
        id: function (state) {
            return state.course_id;
        },
        status: function (state) {
            return state.status || 'error';
        },
        currentRequest: function (state) {
            return state.countCurrentRequest || 0;
        }
    };

    var mutations = {
        'UPDATE_STATUS': function (state, status) {
            state.status = status;
        },
        'INCREASE_NUMBER_REQUEST': function (state) {
            state.countCurrentRequest++;
        },
        'DECREASE_NUMBER_REQUEST': function (state) {
            state.countCurrentRequest--;
        },
        'SET_SECTIONS': function (state, sections) {
            state.sections = sections;
        },
        'ADD_NEW_SECTION': function (state, section) {
            state.sections.push(section);
        },
        'REMOVE_SECTION': function (state, index) {
            state.sections.splice(index, 1);
        }
    };

    var actions = {
        newRequest: function (context) {
            context.commit('INCREASE_NUMBER_REQUEST');
            context.commit('UPDATE_STATUS', 'loading');
        },

        requestComplete: function (context, status) {
            context.commit('DECREASE_NUMBER_REQUEST');

            if (context.getters.currentRequest === 0) {
                context.commit('UPDATE_STATUS', status);
            }
        },

        addNewSection: function (context) {
            Vue.http.LPRequest({type: 'new-section'})
                .then(
                    function (response) {
                        var result = response.body;

                        if (result.success) {
                            context.commit('ADD_NEW_SECTION', result.data);
                        }
                    },
                    function (error) {
                        console.error(error);
                    }
                );
        },

        removeSection: function (context, payload) {
            context.commit('REMOVE_SECTION', payload.index);

            Vue.http.LPRequest({
                type: 'remove-section',
                'section-id': payload.section.id
            }).then(
                function (response) {
                    var result = response.body;
                },
                function (error) {
                    console.error(error);
                }
            );
        },

        updateSections: function (context, sections) {
            Vue.http.LPRequest({sections: sections})
                .then(
                    function (response) {
                        var result = response.body;

                        context.commit('SET_SECTIONS', result.data);
                    },
                    function (error) {
                        console.error(error);
                    }
                );
        }
    };

    /**
     * Vuex Store
     *
     * @type {Store}
     */
    exports.LP_Curriculum_Store = new Vuex.Store({
        state: state,
        getters: getters,
        mutations: mutations,
        actions: actions
    });

})(window, Vue, Vuex, lq_course_editor);

/**
 * HTTP
 */
(function (exports, Vue, $store) {
    Vue.http.LPRequest = function (payload) {
        payload['nonce'] = $store.state.nonce;
        payload['lp-ajax'] = $store.state.action;
        payload['course-id'] = $store.getters.id;

        return Vue.http.post($store.state.ajax,
            payload,
            {
                emulateJSON: true,
                params: {
                    namespace: 'LPCurriculumRequest'
                }
            });
    };

    Vue.http.interceptors.push(function (request, next) {
        if (request.params['namespace'] !== 'LPCurriculumRequest') {
            next();
            return;
        }

        $store.dispatch('newRequest');

        next(function (response) {
            var body = response.body;
            var result = body.success || false;

            if (result) {
                $store.dispatch('requestComplete', 'success');
            } else {
                $store.dispatch('requestComplete', 'failed');
            }
        });
    });
})(window, Vue, LP_Curriculum_Store);

/**
 * Init app.
 */
(function ($, Vue) {
    $(document).ready(function () {
        window.LP_Course_Editor = new Vue({
            el: '#course-editor-v2',
            template: '<lp-course-editor></lp-course-editor>'
        });
    });
})(jQuery, window.Vue);
