const vueApp = new Vue({
    el: '#vapp',
    data: { 
        crs: '',
        name: '',
        content: '',
        description: '',
        config: {},
    },
    mounted: function() {
        const configJSON = document.getElementById('jsenv').innerHTML
        this.config = JSON.parse(configJSON)

        const v = this
        axios.get(this.config.www + '/index.php/api/destination/' + this.config.serviceid + '/' + this.config.destinationid)
        .then(function(response) {
            const data = response.data
            v.crs = data.crs
            v.name = data.name
            v.content = data.description
        })
        .catch(error => {
            iziToast.error({
                'title': 'Error',
                'message': 'Link to server has failed',
            })
        })
    },
    methods: {
        crschange() {
            this.crs = this.crs.toUpperCase()
            const v = this
            axios.get(this.config.www + '/index.php/api/crs/' + this.crs)
            .then((response) => {
                const name = response.data
                if (name) {
                    v.name = name
                }
            })
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed',
                })
            })
        },
        submit() {
            this.description = this.content
        }
    }

})