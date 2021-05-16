const vueApp = new Vue({
    el: '#vapp',
    data: { 
        crs: '',
        name: '',
        config: {},
    },
    mounted: function() {
        const configJSON = document.getElementById('jsenv').innerHTML
        this.config = JSON.parse(configJSON)

        if (this.config.joiningid) {
            const v = this
            axios.get(this.config.www + '/index.php/api/joining/' + this.config.joiningid)
            .then(function(response) {
                const data = response.data
                v.crs = data.crs
                v.name = data.station
            })
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        }
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
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        }
    }

})