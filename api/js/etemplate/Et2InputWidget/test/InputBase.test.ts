/**
 * Common base for easily running some standard tests on all input widgets
 *
 * This file should not get run on its own, extend it
 *
 * TODO: Not sure exactly how to make this happen yet.  Maybe:
 * https://github.com/mochajs/mocha/wiki/Shared-Behaviours
 * <code>
 *     shared:
 *	 exports.shouldBehaveLikeAUser = function() {
 *	  it('should have .name.first', function() {
 *		this.user.name.first.should.equal('tobi');
 *	  })
 *
 *	  it('should have .name.last', function() {
 *		this.user.name.last.should.equal('holowaychuk');
 *	  })
 *
 *	  describe('.fullname()', function() {
 *		it('should return the full name', function() {
 *		  this.user.fullname().should.equal('tobi holowaychuk');
 *		})
 *	  })
 *	};
 *	test.js:
 *
 *	var User = require('./user').User
 *	  , Admin = require('./user').Admin
 *	  , shared = require('./shared');
 *
 *	describe('User', function() {
 *	  beforeEach(function() {
 *		this.user = new User('tobi', 'holowaychuk');
 *	  })
 *
 *	  shared.shouldBehaveLikeAUser();
 *	})
 *     </code>
 */
