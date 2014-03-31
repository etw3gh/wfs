var app = angular.module('plunker', ['siyfion.sfTypeahead']);

app.controller('MainCtrl', function($scope) {

    $scope.selectedNumber = null;

    // instantiate the bloodhound suggestion engine
    var numbers = new Bloodhound({
        datumTokenizer: function(d) { return Bloodhound.tokenizers.whitespace(d.num); },
        queryTokenizer: Bloodhound.tokenizers.whitespace,


        local: [
            { num: 'one' },
            { num: 'two' },
            { num: 'three' },
            { num: 'four' },
            { num: 'five' },
            { num: 'six' },
            { num: 'seven' },
            { num: 'eight' },
            { num: 'nine' },
            { num: 'ten' }
        ]


    });

    // initialize the bloodhound suggestion engine
    numbers.initialize();

    $scope.numbersDataset = {
        displayKey: 'num',
        source: numbers.ttAdapter()
    };

    $scope.addValue = function () {
        numbers.add({
            num: 'twenty'
        });
    };

    // Typeahead options object
    $scope.exampleOptions = {
        highlight: true
    };

});