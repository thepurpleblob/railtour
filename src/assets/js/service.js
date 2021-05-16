const vueApp = new Vue({
    el: '#vapp',
    data: { 
        content: '',
        description: '',
        config: {},
    },
    mounted: function() {
        const configJSON = document.getElementById('jsenv').innerHTML
        this.config = JSON.parse(configJSON)

        const v = this
        axios.get(this.config.www + '/index.php/api/service/' + this.config.serviceid)
        .then(function(response) {
            const data = response.data
            v.content = data.description
        })
        .catch(error => {
            iziToast.error({
                'title': 'Error',
                'message': 'Link to server has failed - ' +error.message,
            })
        })
    },
    methods: {
        submit() {
            this.description = this.content
        }
    }

})